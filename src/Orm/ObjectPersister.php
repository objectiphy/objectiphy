<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Composer\Config;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\UpdateQuery;
use Objectiphy\Objectiphy\Traits\TransactionTrait;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectPersister implements TransactionInterface
{
    use TransactionTrait;

    private bool $disableDeleteRelationships = false;
    private bool $disableDeleteEntities = false;
    private SqlUpdaterInterface $sqlUpdater;
    private ObjectMapper $objectMapper;
    private ObjectUnbinder $objectUnbinder;
    private StorageInterface $storage;
    private EntityTracker $entityTracker;
    private ObjectRemover $objectRemover;
    private SaveOptions $options;
    private array $savedObjects = [];

    public function __construct(
        SqlUpdaterInterface $sqlUpdater,
        ObjectMapper $objectMapper,
        ObjectUnbinder $objectUnbinder,
        StorageInterface $storage,
        EntityTracker $entityTracker
    ) {
        $this->sqlUpdater = $sqlUpdater;
        $this->objectMapper = $objectMapper;
        $this->objectUnbinder = $objectUnbinder;
        $this->storage = $storage;
        $this->entityTracker = $entityTracker;
    }

    public function setObjectRemover(ObjectRemover $objectRemover)
    {
        $this->objectRemover = $objectRemover;
    }
    
    public function setConfigOptions(ConfigOptions $config): void 
    {
        $this->disableDeleteRelationships = $config->disableDeleteRelationships;
        $this->disableDeleteEntities = $config->disableDeleteEntities;
        $this->objectUnbinder->setConfigOptions($config);
        $this->objectRemover->setConfigOptions($config);
    }

    /**
     * Config options relating to persisting data only.
     */
    public function setSaveOptions(SaveOptions $saveOptions): void
    {
        $this->options = $saveOptions;
        $this->sqlUpdater->setSaveOptions($saveOptions);
        $this->objectUnbinder->setMappingCollection($this->options->mappingCollection);
    }

    public function getClassName()
    {
        if (isset($this->options) && isset($this->options->mappingCollection)) {
            return $this->options->mappingCollection->getEntityClassName();
        }

        return '';
    }

    public function setClassName(string $className): void
    {
        $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->setSaveOptions($this->options);
    }

    /**
     * @param object $entity
     * @param SaveOptions $options
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     */
    public function saveEntity(
        object $entity,
        SaveOptions $options,
        int &$insertCount = 0,
        int &$updateCount = 0,
        int &$deleteCount = 0
    ): int {
        $this->setSaveOptions($options);
        $this->savedObjects = []; //Avoid recursion

        return $this->doSaveEntity($entity, $insertCount, $updateCount, $deleteCount);
    }

    /**
     * @param array $entities
     * @param SaveOptions $options
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     */
    public function saveEntities(
        array $entities,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ): int {
        $result = 0;
        foreach ($entities as $entity) {
            $result += $this->saveEntity($entity, $options, $insertCount, $updateCount);
        }

        return $result;
    }

    /**
     * Execute an insert or update query directly
     * @param QueryInterface $query
     * @param SaveOptions $options
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     * @throws QueryException
     */
    public function executeSave(
        QueryInterface $query,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ): int {
        $this->setSaveOptions($options);
        $query->finalise($this->options->mappingCollection);
        if ($query instanceof UpdateQuery) {
            $sql = $this->sqlUpdater->getUpdateSql($query, $this->options->replaceExisting);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $updateCount += $this->storage->getAffectedRecordCount();
            }
        } elseif ($query instanceof InsertQuery) {
            $sql = $this->sqlUpdater->getInsertSql($query);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $insertCount += $this->storage->getAffectedRecordCount();
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
     * @return int Total number of rows updated or inserted
     */
    private function doSaveEntity(
        object $entity,
        int &$insertCount,
        int &$updateCount,
        int &$deleteCount
    ): int {
        $result = 0;
        $originalClassName = $this->getClassName();
        $this->setClassName(ObjectHelper::getObjectClassName($entity));

        //Try to work out if we are inserting or updating
        $update = false;
        $pkValues = $this->options->mappingCollection->getPrimaryKeyValues($entity);
        if ($this->entityTracker->hasEntity($this->getClassName(), $pkValues)) {
            $update = true; //We are tracking it, so it is definitely an update
        } elseif ($pkValues) { //We have values for the primary key so probably an update
            $update = true;
            //Check if the primary key is a foreign key (if so, could be an insert so will need to replace)
            foreach (array_keys($pkValues) as $pkKey) {
                if ($this->options->mappingCollection->getPropertyMapping($pkKey)->isForeignKey) {
                    $this->options->replaceExisting = true;
                    $update = false;
                    break;
                }
            }
        }

        $this->objectRemover->setDeleteOptions(DeleteOptions::create($this->options->mappingCollection));
        $this->objectRemover->checkForRemovals($entity, $updateCount, $deleteCount);
        if ($update) {
            $result = $this->updateEntity($entity, $pkValues, $insertCount, $updateCount, $deleteCount);
        } else {
            $result = $this->insertEntity($entity, $insertCount, $updateCount);
        }
        $this->setClassName($originalClassName);

        return $result;
    }

    /**
     * @param object $entity
     * @param array $pkValues
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     * @throws QueryException
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
        if ($this->options->saveChildren) {
            //Insert new child entities first so that we can populate the foreign keys on the parent
            $this->saveChildren($entity, $insertCount, $updateCount, $deleteCount, true);
        }

        $originalClassName = $this->getClassName();
        $className = ObjectHelper::getObjectClassName($entity);
        $this->setClassName($className);
        $qb = QB::create();
        foreach ($pkValues as $key => $value) {
            $qb->where($key, QB::EQ, $this->objectUnbinder->unbindValue($value));
        }
        $updateQuery = $qb->buildUpdateQuery();
        $this->objectMapper->addExtraMappings($className, $updateQuery);
        $rows = $this->objectUnbinder->unbindEntityToRow($entity, $pkValues, $this->options->saveChildren);
        if ($rows) {
            $updateQuery->finalise($this->options->mappingCollection, $className, $rows);
            $sql = $this->sqlUpdater->getUpdateSql($updateQuery, $this->options->replaceExisting);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $updateCount += $this->storage->getAffectedRecordCount();
                $this->entityTracker->storeEntity($entity, $pkValues);
            }
        }
        $this->savedObjects[] = $entity; //Even if nothing changed, don't attempt to save again
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
     * @throws \ReflectionException
     */
    private function insertEntity(object $entity, int &$insertCount, int &$updateCount): int
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
        $qb = QB::create();
        $insertQuery = $qb->buildInsertQuery();
        $row = $this->objectUnbinder->unbindEntityToRow($entity, [], $this->options->saveChildren);
        if ($row) {
            $insertQuery->finalise($this->options->mappingCollection, $this->getClassName(), $row);
            $sql = $this->sqlUpdater->getInsertSql($insertQuery, $this->options->replaceExisting);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $insertId = $this->storage->getLastInsertId();
                $insertCount += $this->storage->getAffectedRecordCount();
                $pkProperties = $this->options->mappingCollection->getPrimaryKeyProperties($this->getClassName());
                $pkValues = [];
                if ($insertId && count($pkProperties) == 1) {
                    $pkProperty = reset($pkProperties);
                    $pkValues[$pkProperty] = $insertId;
                    ObjectHelper::setValueOnObject($entity, $pkProperty, $insertId);
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

    private function saveChildren(object $entity, int &$insertCount, int &$updateCount, int &$deleteCount, bool $ownedOnly = false): void
    {
        $childProperties = $this->options->mappingCollection->getChildObjectProperties($ownedOnly);
        foreach ($childProperties as $childPropertyName) {
            if ($entity instanceof EntityProxyInterface && $entity->isChildAsleep($childPropertyName)) {
                continue; //Don't wake it up
            }
            $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($childPropertyName);
            $childParentProperty = $childPropertyMapping->relationship->mappedBy;
            $child = ObjectHelper::getValueFromObject($entity, $childPropertyName);
            if (!empty($child)) {
                $childEntities = is_iterable($child) ? $child : [$child];
                foreach ($childEntities as $childEntity) {
                    if (in_array($childEntity, $this->savedObjects)) {
                        continue; //Prevent recursion
                    }
                    if (!($childEntity instanceof ObjectReferenceInterface)) {
                        $childPkValues = $this->options->mappingCollection->getPrimaryKeyValues($childEntity);
                        //Populate parent
                        if ($childParentProperty) {
                            ObjectHelper::setValueOnObject($childEntity, $childParentProperty, $entity);
                        }
                        if (!$childPkValues) {
                            //If child is late bound, we might not know its primary key, so get its own mapping collection
                            $childClass = ObjectHelper::getObjectClassName($childEntity);
                            $childMappingCollection = $this->objectMapper->getMappingCollectionForClass($childClass);
                            $childPkValues = $childMappingCollection->getPrimaryKeyValues($childEntity);
                        }
                        $this->doSaveEntity($childEntity, $insertCount, $updateCount, $deleteCount);
                    }
                }
            }
        }
    }
}
