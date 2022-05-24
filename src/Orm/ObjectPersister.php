<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\InsertQueryInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Traits\TransactionTrait;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectPersister implements TransactionInterface
{
    use TransactionTrait;

    private SqlUpdaterInterface $sqlUpdater;
    private ObjectMapper $objectMapper;
    private ObjectUnbinder $objectUnbinder;
    private StorageInterface $storage;
    private EntityTracker $entityTracker;
    private ObjectRemover $objectRemover;
    private SqlStringReplacer $stringReplacer;
    private ConfigOptions $config;
    private SaveOptions $options;
    private array $savedObjects = [];
    private ExplanationInterface $explanation;
    private ?int $lastInsertId = null;

    public function __construct(
        SqlUpdaterInterface $sqlUpdater,
        ObjectMapper $objectMapper,
        ObjectUnbinder $objectUnbinder,
        StorageInterface $storage,
        EntityTracker $entityTracker,
        SqlStringReplacer $stringReplacer,
        ExplanationInterface $explanation
    ) {
        $this->sqlUpdater = $sqlUpdater;
        $this->objectMapper = $objectMapper;
        $this->objectUnbinder = $objectUnbinder;
        $this->storage = $storage;
        $this->entityTracker = $entityTracker;
        $this->stringReplacer = $stringReplacer;
        $this->explanation = $explanation;
    }

    /**
     * In case we have to remove some child objects from a collection.
     * @param ObjectRemover $objectRemover
     */
    public function setObjectRemover(ObjectRemover $objectRemover): void
    {
        $this->objectRemover = $objectRemover;
    }

    /**
     * @param ConfigOptions $config
     * @throws ObjectiphyException
     */
    public function setConfigOptions(ConfigOptions $config): void
    {
        $this->config = $config;
        $this->objectUnbinder->setConfigOptions($config);
        $this->objectRemover->setConfigOptions($config);
    }

    /**
     * Config options relating to persisting data only.
     * @param SaveOptions $saveOptions
     */
    public function setSaveOptions(SaveOptions $saveOptions): void
    {
        $this->options = $saveOptions;
        $this->sqlUpdater->setSaveOptions($saveOptions);
        $this->objectUnbinder->setMappingCollection($this->options->mappingCollection);
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        if (isset($this->options) && isset($this->options->mappingCollection)) {
            return $this->options->mappingCollection->getEntityClassName();
        }

        return '';
    }

    /**
     * @param string $className
     * @return string Original class name before it was changed
     * @throws ObjectiphyException|\ReflectionException
     */
    public function setClassName(string $className): string
    {
        $origClassName = $this->options->mappingCollection->getEntityClassName();
        $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->setSaveOptions($this->options);

        return $origClassName;
    }

    /**
     * @return int|null
     */
    public function getLastInsertId(): ?int
    {
        return $this->lastInsertId;
    }

    /**
     * @param object $entity
     * @param SaveOptions $options
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @param int $deleteCount
     * @return int Total number of rows updated or inserted
     * @throws QueryException|\ReflectionException|ObjectiphyException
     */
    public function saveEntity(
        object $entity,
        SaveOptions $options,
        int &$insertCount = 0,
        int &$updateCount = 0,
        int &$deleteCount = 0,
        bool $storeInsertId = false
    ): int {
        $this->setSaveOptions($options);
        $this->savedObjects = []; //Avoid recursion

        return $this->doSaveEntity($entity, $insertCount, $updateCount, $deleteCount, $storeInsertId);
    }

    /**
     * @param array $entities
     * @param SaveOptions $options
     * @param int|null $insertCount Number of rows inserted
     * @param int|null $updateCount Number of rows updated
     * @param int|null $deleteCount
     * @return int Total number of rows updated or inserted
     * @throws QueryException|\ReflectionException|ObjectiphyException
     */
    public function saveEntities(
        array $entities,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null,
        ?int &$deleteCount = null
    ): int {
        $result = 0;
        foreach ($entities as $entity) {
            $result += $this->saveEntity($entity, $options, $insertCount, $updateCount, $deleteCount);
        }

        return $result;
    }

    /**
     * Execute an insert or update query directly
     * @param QueryInterface $query
     * @param SaveOptions $options
     * @param int|null $insertCount Number of rows inserted
     * @param int|null $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     * @throws ObjectiphyException|QueryException|\ReflectionException
     */
    public function executeSave(
        QueryInterface $query,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ): int {
        $this->setSaveOptions($options);
        $this->objectMapper->addExtraMappings($this->getClassName(), $query);
        $this->objectMapper->addExtraClassMappings($this->getClassName(), $query);
        $query->finalise($this->options->mappingCollection, $this->stringReplacer);
        if ($query instanceof UpdateQueryInterface) {
            $sql = $this->sqlUpdater->getUpdateSql($query, $this->options->replaceExisting, $this->options->parseDelimiters);
            $this->explanation->addQuery($query, $sql, $this->options->mappingCollection, $this->config);
            if ($this->storage->executeQuery($sql, $query->getParams())) {
                $updateCount += $this->storage->getAffectedRecordCount();
            }
        } elseif ($query instanceof InsertQueryInterface) {
            $sql = $this->sqlUpdater->getInsertSql($query, $options->replaceExisting, $this->options->parseDelimiters);
            $this->explanation->addQuery($query, $sql, $this->options->mappingCollection, $this->config);
            if ($this->storage->executeQuery($sql, $query->getParams())) {
                $insertCount += $this->storage->getAffectedRecordCount();
                $this->lastInsertId = $this->storage->getLastInsertId();
            }
        } else {
            throw new QueryException('Only update or insert queries can be executed by ObjectPersister');
        }

        return $insertCount + $updateCount;
    }

    /**
     * @param object $entity
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @param int $deleteCount
     * @return int Total number of rows updated or inserted
     * @throws QueryException|\ReflectionException|ObjectiphyException
     */
    private function doSaveEntity(
        object $entity,
        int &$insertCount,
        int &$updateCount,
        int &$deleteCount,
        bool $storeInsertId = false
    ): int {
        $originalClassName = $this->getClassName();
        $this->setClassName(ObjectHelper::getObjectClassName($entity));
        $this->options->mappingCollection->setGroups(
            $this->config->hydrateUngroupedProperties,
            ...$this->config->serializationGroups
        );

        //Try to work out if we are inserting or updating
        $update = false;
        $pkValues = $this->options->mappingCollection->getPrimaryKeyValues($entity);
        if ($this->entityTracker->hasEntity($entity, $pkValues)) {
            //We are tracking it, so it is definitely an update, but if we don't have a hydrated pk, throw up
            if (!$pkValues) {
                throw new QueryException('Cannot save a partially hydrated object if there is no primary key.');
            }
            $update = true;
        } elseif ($pkValues) { //We have values for the primary key so probably an update
            $update = !$this->options->replaceExisting;
            //Check if the primary key is a foreign key or does not auto-increment (if so, could be an insert so will need to replace)
            if ($update) {
                foreach (array_keys($pkValues) as $pkKey) {
                    $pkPropertyMapping = $this->options->mappingCollection->getPropertyMapping($pkKey);
                    if ($pkPropertyMapping->column->autoIncrement === false || $pkPropertyMapping->isForeignKey) {
                        $this->options->replaceExisting = true;
                        $update = false;
                        break;
                    }
                }
            }
        }

        if ($update) {
            $this->objectRemover->setDeleteOptions(DeleteOptions::create($this->options->mappingCollection));
            $this->objectRemover->checkForRemovals($entity, $updateCount, $deleteCount);
            $result = $this->updateEntity($entity, $pkValues, $insertCount, $updateCount, $deleteCount);
        } else {
            $result = $this->insertEntity($entity, $insertCount, $updateCount, $storeInsertId);
        }
        $this->setClassName($originalClassName);

        return $result;
    }

    /**
     * @param object $entity
     * @param array $pkValues
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @param int $deleteCount
     * @return int Total number of rows updated or inserted
     * @throws QueryException|ObjectiphyException|\ReflectionException
     */
    private function updateEntity(
        object $entity,
        array $pkValues,
        int &$insertCount,
        int &$updateCount,
        int &$deleteCount
    ): int {
        if (in_array($entity, $this->savedObjects)) {
            return 0;
        }
        $this->savedObjects[] = $entity; //Even if nothing changed, don't attempt to save again
        if ($this->options->saveChildren) {
            //Insert new child entities first so that we can populate the foreign keys on the parent
            $this->saveChildren($entity, $insertCount, $updateCount, $deleteCount, true);
        }

        $originalClassName = $this->getClassName();
        $className = ObjectHelper::getObjectClassName($entity);
        $this->setClassName($className);
        $this->options->mappingCollection->getRelationships(); //Ensures all mapping is populated even if mapped by other side
        $qb = QB::create();
        foreach ($pkValues as $key => $value) {
            $qb->where($key, QB::EQ, $this->objectUnbinder->unbindValue($value));
        }
        $updateQuery = $qb->buildUpdateQuery();
        $this->objectMapper->addExtraMappings($className, $updateQuery);
        $rows = $this->objectUnbinder->unbindEntityToRow($entity, $pkValues, $this->options->saveChildren);
        //Even if there are no rows, still finalise in cases children need relationship mappings afterwards
        $updateQuery->finalise($this->options->mappingCollection, $this->stringReplacer, $className, $rows);
        if ($rows) {
            $sql = $this->sqlUpdater->getUpdateSql($updateQuery, $this->options->replaceExisting, $this->options->parseDelimiters);
            $this->explanation->addQuery($updateQuery, $sql, $this->options->mappingCollection, $this->config);
            if ($this->storage->executeQuery($sql, $updateQuery->getParams())) {
                $updateCount += $this->storage->getAffectedRecordCount();
                $this->entityTracker->storeEntity($entity, $pkValues);
            }
        }
        if ($this->options->saveChildren) {
            $this->saveChildren($entity, $insertCount, $updateCount, $deleteCount);
        }
        $this->setClassName($originalClassName);

        return $insertCount + $updateCount + $deleteCount;
    }

    /**
     * @param object $entity
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     * @throws \ReflectionException|ObjectiphyException
     */
    private function insertEntity(object $entity, int &$insertCount, int &$updateCount, bool $storeInsertId = false): int
    {
        if (in_array($entity, $this->savedObjects)) {
            return 0;
        }
        $deleteCount = 0; //There won't be any for an insert!

        $this->savedObjects[] = $entity; //Don't save it as a child of a child, do it after the children are saved
        if ($this->options->saveChildren) {
            //First, save any child objects which are owned by the parent
            $this->saveChildren($entity, $insertCount, $updateCount, $deleteCount, true);
        }

        //Then save the parent
        $originalClassName = $this->getClassName();
        $this->setClassName(ObjectHelper::getObjectClassName($entity));
        $this->options->mappingCollection->getRelationships(); //Ensures all mapping is populated even if mapped by other side
        $qb = QB::create();
        $insertQuery = $qb->buildInsertQuery();
        $row = $this->objectUnbinder->unbindEntityToRow($entity, [], $this->options->saveChildren);
        //Even if there is no row, still finalise in cases children need relationship mappings afterwards
        $insertQuery->finalise($this->options->mappingCollection, $this->stringReplacer, $this->getClassName(), $row);
        if ($row) {
            $sql = $this->sqlUpdater->getInsertSql($insertQuery, $this->options->replaceExisting, $this->options->parseDelimiters);
            $this->explanation->addQuery($insertQuery, $sql, $this->options->mappingCollection, $this->config);
            if ($this->storage->executeQuery($sql, $insertQuery->getParams())) {
                $lastInsertId = $this->storage->getLastInsertId();
                if ($storeInsertId) {
                    $this->lastInsertId = $lastInsertId;
                }
                $duplicateUpdated = false;
                $insertCount += $this->storage->getAffectedRecordCount($duplicateUpdated);
                if ($duplicateUpdated) {
                    $insertCount--;
                    $updateCount++;
                }
                $pkProperties = $this->options->mappingCollection->getPrimaryKeyProperties($this->getClassName());
                $pkValues = [];
                if ($lastInsertId && count($pkProperties) == 1) {
                    $pkProperty = reset($pkProperties);
                    $pkValues[$pkProperty] = $lastInsertId;
                    ObjectHelper::setValueOnObject($entity, $pkProperty, $lastInsertId);
                }
                $this->entityTracker->storeEntity($entity, $pkValues);
            }
        }
        $this->setClassName($originalClassName);

        if ($this->options->saveChildren) {
            //Then save any child objects which are owned by the child
            $this->saveChildren($entity, $insertCount, $updateCount, $deleteCount);
        }

        return $insertCount;
    }

    /**
     * @param object $entity
     * @param int $insertCount
     * @param int $updateCount
     * @param int $deleteCount
     * @param bool $ownedOnly
     * @throws ObjectiphyException|QueryException|\ReflectionException
     */
    private function saveChildren(
        object $entity,
        int &$insertCount,
        int &$updateCount,
        int &$deleteCount,
        bool $ownedOnly = false
    ): void {
        static $depth = 0;
        $depth++;
        if ($this->config->disableEntityCache && $depth > $this->config->maxDepth) {
            //Without the entity cache, we won't track what has changed and will try to save everything
            $depth--;
            return;
        }

        $childProperties = $this->options->mappingCollection->getChildObjectProperties($ownedOnly);

        foreach ($childProperties as $childPropertyName) {
            if (($entity instanceof EntityProxyInterface && $entity->isChildAsleep($childPropertyName))
                || (property_exists($entity, '__isInitialized__') && $entity->__isInitialized__ === false)) {
                continue; //Don't wake it up
            }
            $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($childPropertyName);
            $childParentProperty = $childPropertyMapping->relationship->mappedBy;
            if ($childPropertyMapping->relationship->isEmbedded) {
                continue;
            }
            $child = ObjectHelper::getValueFromObject($entity, $childPropertyName);
            if (!empty($child)) {
                $childEntities = is_iterable($child) ? $child : [$child];
                foreach ($childEntities as $childEntity) {
                    if (!in_array($childEntity, $this->savedObjects)) {
                        $childEntityPkValues = $this->options->mappingCollection->getPrimaryKeyValues($childEntity);
                        if (!$childEntityPkValues) { //If we don't already have it, try to get it by loading the mapping for the child
                            try {
                                $origClass = $this->setClassName(ObjectHelper::getObjectClassName($childEntity));
                                $childEntityPkValues = $this->options->mappingCollection->getPrimaryKeyValues($childEntity);
                                $this->setClassName($origClass);
                            } catch (\Exception $ex) {
                                //Don't panic, assume pk of id, and if that fails we'll panic then.
                            }
                        }
                        if (!$childEntityPkValues) {
                            //If we have a pk, this is an insert - if we don't, we cannot do anything with it
                            $childPkProperties = $this->options->mappingCollection->getPrimaryKeyProperties(
                                $childPropertyMapping->getChildClassName()
                            );
                        }
                        if (($childEntityPkValues || $childPkProperties)
                            && !($childEntity instanceof ObjectReferenceInterface)
                            && $this->entityTracker->isEntityDirty($childEntity, $childEntityPkValues)
                        ) {
                            //Populate parent
                            if ($childParentProperty && !$childPropertyMapping->relationship->isManyToMany()) {
                                ObjectHelper::setValueOnObject($childEntity, $childParentProperty, $entity);
                            }
                            $this->doSaveEntity($childEntity, $insertCount, $updateCount, $deleteCount);
                        }
                    }
                }
                if (!$ownedOnly && $childPropertyMapping->relationship->isManyToMany()) {
                    //Children saved, but bridging table also needs populating
                    $this->saveManyToMany($entity, $childPropertyMapping, $childEntities, $insertCount, $updateCount);
                }
            }
        }

        $depth--;
    }

    private function saveManyToMany(
        object $parentEntity,
        PropertyMapping $propertyMapping,
        iterable $childEntities,
        int &$insertCount,
        int &$updateCount
    ) {
        $pkValues = $this->options->mappingCollection->getPrimaryKeyValues($parentEntity);
        if ($this->entityTracker->getDirtyProperties($parentEntity, $pkValues, [$propertyMapping->propertyName])) {
            $sourceColumn = $propertyMapping->relationship->bridgeSourceJoinColumn;
            $targetColumn = $propertyMapping->relationship->bridgeTargetJoinColumn;
            $parentKeyValues = $this->buildKeyValues($parentEntity, $sourceColumn, $pkValues);
            $qb = QB::create()->insert($this->stringReplacer->delimit($propertyMapping->relationship->bridgeJoinTable));
            foreach ($childEntities as $childEntity) {
                $childKeyValues = $this->buildKeyValues($childEntity, $targetColumn);
                $qb->set(array_merge($parentKeyValues, $childKeyValues));
            }
            $query = $qb->buildInsertQuery();
            $this->options->replaceExisting = true;
            $this->executeSave($query, $this->options, $insertCount, $updateCount);
        }
    }

    private function buildKeyValues(object $entity, string $joinColumnMapping, array $pkValues = [])
    {
        $pk = array_values($pkValues ?: $this->options->mappingCollection->getPrimaryKeyValues($entity));
        $keyValues = [];
        foreach (explode(',', $joinColumnMapping) as $index => $column) {
            $keyValues[$this->stringReplacer->delimit($column)] = $pk[$index] ?? null;
        }

        return $keyValues;
    }
}
