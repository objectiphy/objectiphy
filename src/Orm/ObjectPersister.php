<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\Query;
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
    public function beginTransaction()
    {
        $this->storage->beginTransaction();
    }

    /**
     * Commit a transaction that was started manually (if supported by the storage engine)
     */
    public function commitTransaction()
    {
        $this->storage->commitTransaction();
    }

    /**
     * Rollback a transaction that was started manually (if supported by the storage engine)
     */
    public function rollbackTransaction()
    {
        $this->storage->rollbackTransaction();
    }

    /**
     * Config options relating to persisting data only.
     */
    public function setSaveOptions(SaveOptions $saveOptions)
    {
        $this->options = $saveOptions;
        $this->sqlUpdater->setSaveOptions($saveOptions);
        $this->objectUnbinder->setMappingCollection($saveOptions->mappingCollection);
    }

    public function saveEntity(
        object $entity,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ): int {
        $className = ObjectHelper::getObjectClassName($entity);
        $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->setSaveOptions($options);
        $result = 0;

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
            $result = $this->insertEntity($entity, $insertCount);
        }

        return $result;
    }

    /**
     * TODO: Perhaps something more efficient with a single query? Is that even possible?
     */
    public function saveEntities(
        array $entities,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ) {
        $result = 0;
        foreach ($entities as $entity) {
            $result += $this->saveEntity($entity, $options, $insertCount, $updateCount);
        }

        return $result;
    }

    public function saveBy(
        Query $query,
        SaveOptions $options,
        ?int &$insertCount = null,
        ?int &$updateCount = null
    ) {
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

    private function updateEntity(
        object $entity,
        array $pkValues,
        int &$insertCount,
        int &$updateCount
    ): int {
        if ($this->options->saveChildren) {
            //Insert new child entities first so that we can populate the foreign keys on the parent

            //Count inserts
        }

        $className = ObjectHelper::getObjectClassName($entity);
        $qb = QB::create();
        //$qb->update($className)->set($rows);
        foreach ($pkValues as $key => $value) {
            $qb->where($key, QB::EQ, $value);
        }
        $updateQuery = $qb->buildUpdateQuery();
        $this->objectMapper->addExtraMappings($className, $updateQuery);
        $rows = $this->objectUnbinder->unbindEntityToRows($entity, $pkValues, $this->options->saveChildren);
        if ($rows) {
            $updateQuery->finalise($this->options->mappingCollection, $className, $rows);
            $sql = $this->sqlUpdater->getUpdateSql($updateQuery, $this->options->replaceExisting);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $updateCount += $this->storage->getAffectedRecordCount();
                $this->entityTracker->storeEntity($entity, $pkValues);
            }

            if ($this->options->saveChildren) {
                $this->updateChildren($entity, $insertCount, $updateCount);
            }
        }

        return $insertCount + $updateCount;
    }

    private function updateChildren(object $entity, int &$insertCount, int &$updateCount)
    {
        $children = $this->options->mappingCollection->getChildObjectProperties();
        foreach ($children as $childPropertyName) {
            $child = $entity->$childPropertyName ?? null;
            if (!empty($child)) {
                $childPkValues = $this->options->mappingCollection->getPrimaryKeyValues($child);
                if ($childPkValues) {
                    $childEntities = is_iterable($child) ? $child : [$child];
                    foreach ($childEntities as $childEntity) {
                        $this->saveEntity($childEntity, $this->options, $insertCount, $updateCount);
                    }
                }
            }
        }
    }

    private function insertEntity(object $entity, int &$insertCount): int
    {
        return 0;
    }
}
