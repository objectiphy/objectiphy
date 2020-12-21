<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Traits\TransactionTrait;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectRemover implements TransactionInterface
{
    use TransactionTrait;

    private ObjectMapper $objectMapper;
    private SqlDeleterInterface $sqlDeleter;
    private SqlUpdaterInterface $sqlUpdater;
    private EntityTracker $entityTracker;
    private DeleteOptions $options;
    private bool $disableDeleteRelationships = false;
    private bool $disableDeleteEntities = false;

    public function __construct(
        ObjectMapper $objectMapper,
        SqlDeleterInterface $sqlDeleter,
        SqlUpdaterInterface $sqlUpdater,
        StorageInterface $storage,
        EntityTracker $entityTracker
    ) {
        $this->objectMapper = $objectMapper;
        $this->sqlDeleter = $sqlDeleter;
        $this->sqlUpdater = $sqlUpdater;
        $this->storage = $storage;
        $this->entityTracker = $entityTracker;
    }

    public function setConfigOptions(
        bool $disableDeleteRelationships,
        bool $disableDeleteEntities
    ): void {
        $this->disableDeleteRelationships = $disableDeleteRelationships;
        $this->disableDeleteEntities = $disableDeleteEntities;
    }

    /**
     * Config options relating to deleting data only.
     */
    public function setDeleteOptions(DeleteOptions $deleteOptions): void
    {
        $this->options = $deleteOptions;
        $this->sqlDeleter->setDeleteOptions($deleteOptions);
    }

    public function setClassName(string $className): void
    {
        $mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->options->mappingCollection = $mappingCollection;
        $this->setDeleteOptions($this->options);
    }

    public function deleteEntity(object $entity, DeleteOptions $deleteOptions, int &$updateCount): int
    {
        if ($this->disableDeleteEntities) {
            return 0;
        }
        $deleteCount = 0;
        $this->setDeleteOptions($deleteOptions);
        $this->removeChildren($entity, $updateCount, $deleteCount);

        //Delete entity
        $qb = QB::create();
        $deleteQuery = $qb->buildDeleteQuery();

        return $deleteCount + $this->executeDelete($deleteQuery);
    }

    public function executeDelete(DeleteQueryInterface $deleteQuery)
    {
        $deleteCount = 0;
        $className = $deleteQuery->getDelete() ?: $this->options->mappingCollection->getEntityClassName();
        $deleteQuery->finalise($this->options->mappingCollection, $className);

        //RSW: TODO: Can we cascade?

        $sql = $this->sqlDeleter->getDeleteSql($deleteQuery);
        $params = $this->sqlDeleter->getQueryParams();
        if ($sql && $this->storage->executeQuery($sql, $params)) {
            $deleteCount = $this->storage->getAffectedRecordCount();
            $this->entityTracker->clear($className);
        }

        return $deleteCount;
    }

    public function deleteEntities(
        iterable $entities,
        DeleteOptions $deleteOptions,
        int &$updateCount
    ): int {
        if ($this->disableDeleteEntities) {
            return 0;
        }
        $deleteCount = 0;
        $this->setDeleteOptions($deleteOptions);

        //Extract primary keys by class (just in case we have a mixture of entities)
        $deletes = [];
        foreach ($entities as $entity) {
            $className = ObjectHelper::getObjectClassName($entity);
            $pkValues = $this->options->mappingCollection->getPrimaryKeyValues($entity);
            if (empty($pkValues)) {
                throw new ObjectiphyException('Cannot delete an entity which has no primary key value.');
            }
            foreach ($pkValues as $key => $value) {
                $deletes[$className][$key][] = $value;
            }
            $this->removeChildren($entity, $updateCount, $deleteCount);
        }

        if ($deletes) {
            //Delete en-masse per class
            $originalClassName = $this->options->getClassName();
            foreach ($deletes as $className => $pkValues) {
                $this->setClassName($className);
                $deleteQuery = QB::create()->delete($className);
                $valueCount = count(reset($pkValues));
                for ($valueIndex = 0; $valueIndex < $valueCount; $valueIndex++) {
                    $deleteQuery->orStart();
                    foreach ($pkValues as $propertyName => $values) {
                        $deleteQuery->and($propertyName, QB::EQ, $values[$valueIndex]);
                    }
                    $deleteQuery->orEnd();
                }
                $deleteCount += $this->executeDelete($deleteQuery->buildDeleteQuery());
            }
            $this->setClassName($originalClassName);
        }

        return $deleteCount;
    }

    private function removeChildren(object $entity, int &$updateCount, int &$deleteCount)
    {
        $removedChildren = [];
        $children = $this->options->mappingCollection->getChildObjectProperties();
        foreach ($children as $childPropertyName) {
            $child = $entity->$childPropertyName ?? null;
            if (!empty($child)) {
                $children = is_iterable($child) ? $child : [$child];
                $this->sendOrphanedKidsAway($childPropertyName, $children, $updateCount, $deleteCount);
            }
        }
    }

    /**
     * @param string $propertyName Name of property on parent object that points to this child or children
     * @param array $orphans One or more children whose parent has been deleted
     * @throws \ReflectionException
     */
    public function sendOrphanedKidsAway(
        string $propertyName,
        iterable $removedChildren,
        int &$updateCount,
        int &$deleteCount
    ) {
        $goingToOrphanage = [];
        $goingToBelize = [];
        $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($propertyName);
        foreach ($removedChildren as $removedChild) {
            $oneWayTicket = false;
            if (!$this->disableDeleteEntities
                && !$this->options->disableCascade
                && ($childPropertyMapping->relationship->cascadeDeletes
                    || $childPropertyMapping->relationship->orphanRemoval)
            ) { //Nobody wants this baby
                $goingToBelize[] = $removedChild;
                $oneWayTicket = true;
            } else { //Available for adoption
                $parentProperty = $childPropertyMapping->relationship->mappedBy;
                if ($parentProperty && !$this->disableDeleteRelationships) {
                    $goingToOrphanage[] = $removedChild;
                }
            }
        }

        if ($goingToOrphanage) {
            $this->sendKidsToOrphanage($goingToOrphanage, $parentProperty, $updateCount);
        }
        if ($goingToBelize) { //Sorry kids, nothing personal.
            $this->deleteEntities($goingToBelize, $this->options, $deleteCount);
        }
    }

    private function sendKidsToOrphanage(array $orphans, string $parentProperty, &$updateCount)
    {
        if ($orphans && $parentProperty) {
            $childClass = ObjectHelper::getObjectClassName(reset($orphans));
            $pkProperties = $this->options->mappingCollection->getPrimaryKeyProperties($childClass);
            $qb = QB::create()
                ->update($childClass)
                ->set([$parentProperty => null]);
            foreach ($orphans as $orphan) {
                $qb->orStart();
                foreach ($pkProperties as $property) {
                    $qb->where($property, QB::EQ, ObjectHelper::getValueFromObject($orphan, $property));
                }
                $qb->orEnd();
                ObjectHelper::setValueOnObject($orphan, $parentProperty, null);
            }
            $query = $qb->buildUpdateQuery();
            
            $updaterMappingCollection = $this->objectMapper->getMappingCollectionForClass($childClass);
            $this->sqlUpdater->setSaveOptions(SaveOptions::create($updaterMappingCollection));
            $sql = $this->sqlUpdater->getUpdateSql($query);
            $params = $this->sqlUpdater->getQueryParams();
            if ($this->storage->executeQuery($sql, $params)) {
                $updateCount += $this->storage->getAffectedRecordCount();
            }
        }
    }
}
