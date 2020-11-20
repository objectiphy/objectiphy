<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;

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

    public function saveEntity(object $entity, SaveOptions $options)
    {
        $this->setSaveOptions($options);
        $result = null;

        //Try to work out if we are inserting or updating
        $update = false;
        $className = $this->options->mappingCollection->getEntityClassName();
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

        $this->sqlUpdater->setSaveOptions($this->options);
        if ($update) {
            $result = $this->updateEntity($entity, $pkValues);
        } else {
            $result = $this->insertEntity($entity);
        }

        return $result;
    }

    /**
     * TODO: Perhaps something more efficient with a single query? Is that even possible?
     */
    public function saveEntities(array $entities)
    {
        $results = [];
        $this->storage->beginTransaction();
        try {
            foreach ($entities as $entity) {
                $results[] = $this->saveEntity($entity, $updateChildren, $replace);
            }
            $this->storage->commitTransaction();
        } catch (\Throwable $ex) {
            $this->storage->rollbackTransaction();
            throw $ex;
        }

        return $results;
    }

    private function updateEntity(object $entity, array $pkValues): ?int
    {
        if ($this->options->saveChildren) {
            //Insert new child entities first so that we can populate the foreign keys on the parent
        }

        $rows = $this->objectUnbinder->unbindEntityToRows($entity, $pkValues, $this->options->saveChildren);
        if ($rows) {
            $className = ObjectHelper::getObjectClassName($entity);
            $table = $this->options->mappingCollection->getTableForClass($className);
            $sql = $this->sqlUpdater->getUpdateSql($table, $rows, $pkValues);
        }
    }

    private function insertEntity(object $entity)
    {

    }
}
