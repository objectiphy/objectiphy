<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\UpdateQuery;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectPersister
{
    private SqlUpdaterInterface $sqlUpdater;
    private ObjectMapper $objectMapper;
    private ObjectUnbinder $objectUnbinder;
    private StorageInterface $storage;
    private EntityTracker $entityTracker;
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

    /**
     * Manually begin a transaction (if supported by the storage engine)
     */
    public function beginTransaction(): void
    {
        $this->storage->beginTransaction();
    }

    /**
     * Commit a transaction that was started manually (if supported by the storage engine)
     */
    public function commitTransaction(): void
    {
        $this->storage->commitTransaction();
    }

    /**
     * Rollback a transaction that was started manually (if supported by the storage engine)
     */
    public function rollbackTransaction(): void
    {
        $this->storage->rollbackTransaction();
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
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ): int {
        $this->setSaveOptions($options);
        $this->savedObjects = []; //Avoid recursion

        return $this->doSaveEntity($entity, $insertCount, $updateCount);
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
    public function saveBy(
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
     * When saving entities, we need the mapping collection for the current entity as we traverse the object hierarchy.
     * After saving child entities, we reset it back to the original (parent) entity's mapping, ready for the next call.
     * @param string $className
     * @return string Whatever the class name was before the update
     */
    private function updateMappingCollection(string $className): string
    {
        $originalClassName = '';
        if (isset($this->options) && isset($this->options->mappingCollection)) { //Roll on PHP 8
            $originalClassName = $this->options->mappingCollection->getEntityClassName();
        }
        $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->setSaveOptions($this->options);

        return $originalClassName;
    }

    /**
     * @param object $entity
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @return int Total number of rows updated or inserted
     */
    private function doSaveEntity(
        object $entity,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ): int {
        $result = 0;
        $className = ObjectHelper::getObjectClassName($entity);
        $originalClassName = $this->updateMappingCollection($className);

        //Try to work out if we are inserting or updating
        $update = false;
        $pkValues = $this->options->mappingCollection->getPrimaryKeyValues($entity);
        if ($this->entityTracker->hasEntity($className, $pkValues)) {
            //We are tracking it, so it is definitely an update
            $update = true;
        } elseif ($pkValues) { //We have values for the primary key so probably an update
            //Check if the primary key is a foreign key (if so, could be an insert so will need to replace)
            foreach (array_keys($pkValues) as $pkKey) {
                if ($this->options->mappingCollection->getPropertyMapping($pkKey)->isForeignKey) {
                    $this->options->replaceExisting = true;
                    break;
                }
            }
            $update = true;
        }

        if ($update) {
            $result = $this->updateEntity($entity, $pkValues, $insertCount, $updateCount);
        } else {
            $result = $this->insertEntity($entity, $insertCount, $updateCount);
        }
        $this->updateMappingCollection($originalClassName);

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
        int &$updateCount
    ): int {
        if (in_array($entity, $this->savedObjects)) {
            return 0;
        }
        if ($this->options->saveChildren) {
            //Insert new child entities first so that we can populate the foreign keys on the parent
            $this->insertChildren($entity, $insertCount, $updateCount, true);
        }

        $className = ObjectHelper::getObjectClassName($entity);
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
                $this->savedObjects[] = $entity;
                $this->entityTracker->storeEntity($entity, $pkValues);
            }
        }
        if ($this->options->saveChildren) {
            $this->updateChildren($entity, $insertCount, $updateCount);
        }

        return $insertCount + $updateCount;
    }

    /**
     * @param object $entity
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @throws \ReflectionException
     */
    private function updateChildren(object $entity, int &$insertCount, int &$updateCount): void
    {
        $children = $this->options->mappingCollection->getChildObjectProperties();
        foreach ($children as $childPropertyName) {
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
                        continue;
                    }
                    //Populate parent
                    if ($childParentProperty) {
                        ObjectHelper::setValueOnObject($childEntity, $childParentProperty, $entity);
                    }
                    $childPkValues = $this->options->mappingCollection->getPrimaryKeyValues($childEntity);
                    if (!$childPkValues) {
                        //If child is late bound, we might not know its primary key, so get its own mapping collection
                        $childClass = ObjectHelper::getObjectClassName($childEntity);
                        $childMappingCollection = $this->objectMapper->getMappingCollectionForClass($childClass);
                        $childPkValues = $childMappingCollection->getPrimaryKeyValues($childEntity);
                    }
                    $this->doSaveEntity($childEntity, $insertCount, $updateCount);
                }
            }
        }
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
        $className = ObjectHelper::getObjectClassName($entity);

        $this->savedObjects[] = $entity; //Don't save it as a child of a child, do it after the children are saved
        if ($this->options->saveChildren) {
            //First, save any child objects which are owned by the parent
            $this->insertChildren($entity, $insertCount, $updateCount, true);
        }

        //Then save the parent
        $qb = QB::create();
        $insertQuery = $qb->buildInsertQuery();
        $row = $this->objectUnbinder->unbindEntityToRow($entity, [], $this->options->saveChildren);
        if ($row) {
            $insertQuery->finalise($this->options->mappingCollection, $className, $row);
            $sql = $this->sqlUpdater->getInsertSql($insertQuery);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $insertId = $this->storage->getLastInsertId();
                $insertCount += $this->storage->getAffectedRecordCount();
                $pkProperties = $this->options->mappingCollection->getPrimaryKeyProperties($className);
                $pkValues = [];
                if ($insertId && count($pkProperties) == 1) {
                    $pkProperty = reset($pkProperties);
                    $pkValues[$pkProperty] = $insertId;
                    ObjectHelper::setValueOnObject($entity, $pkProperty, $insertId);
                }
                $this->entityTracker->storeEntity($entity, $pkValues);

            }
        }

        if ($this->options->saveChildren) {
            //Then save any child objects which are owned by the child
            $this->insertChildren($entity, $insertCount, $updateCount, false);
        }

        return $insertCount;
    }

    /**
     * @param object $entity
     * @param int $insertCount Number of rows inserted
     * @param int $updateCount Number of rows updated
     * @param bool $ownedOnly Whether to only insert children owned by the entity (must be true if the parent entity
     * has not yet been saved - otherwise the child that gets inserted will be an orphan in the database).
     * @throws \ReflectionException
     */
    private function insertChildren(object $entity, int &$insertCount, int &$updateCount, bool $ownedOnly = true): void
    {
        $children = $this->options->mappingCollection->getChildObjectProperties($ownedOnly);
        foreach ($children as $childPropertyName) {
            $child = $entity->$childPropertyName ?? null;
            if (!empty($child)) {
                $childEntities = is_iterable($child) ? $child : [$child];
                foreach ($childEntities as $childEntity) {
                    if (!($childEntity instanceof ObjectReferenceInterface)) {
                        $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($childPropertyName);
                        $childParentProperty = $childPropertyMapping->relationship->mappedBy;
                        $childPkValues = $this->options->mappingCollection->getPrimaryKeyValues($childEntity);
                        if (!$childPkValues) {
                            //Populate parent
                            if ($childParentProperty) {
                                ObjectHelper::setValueOnObject($childEntity, $childParentProperty, $entity);
                            }
                            $this->doSaveEntity($childEntity, $insertCount, $updateCount);
                        }
                    }
                }
            }
        }
    }
}
