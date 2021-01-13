<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Traits\TransactionTrait;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectRemover implements TransactionInterface
{
    use TransactionTrait;

    private ObjectMapper $objectMapper;
    private SqlDeleterInterface $sqlDeleter;
    private StorageInterface $storage;
    private ObjectPersister $objectPersister;
    private ObjectFetcher $objectFetcher;
    private EntityTracker $entityTracker;
    private DeleteOptions $options;
    private bool $disableDeleteRelationships = false;
    private bool $disableDeleteEntities = false;
    private ExplanationInterface $explanation;
    private ConfigOptions $config;

    public function __construct(
        ObjectMapper $objectMapper,
        SqlDeleterInterface $sqlDeleter,
        StorageInterface $storage,
        ObjectFetcher $objectFetcher,
        EntityTracker $entityTracker,
        ExplanationInterface $explanation
    ) {
        $this->objectMapper = $objectMapper;
        $this->sqlDeleter = $sqlDeleter;
        $this->storage = $storage;
        $this->objectFetcher = $objectFetcher;
        $this->entityTracker = $entityTracker;
        $this->explanation = $explanation;
    }

    /**
     * Deleting relationships requires an update to the parent.
     * @param ObjectPersister $objectPersister
     */
    public function setObjectPersister(ObjectPersister $objectPersister): void
    {
        $this->objectPersister = $objectPersister;
    }

    /**
     * @param ConfigOptions $config
     * @throws ObjectiphyException
     */
    public function setConfigOptions(ConfigOptions $config): void
    {
        $this->config = $config;
        $this->objectFetcher->setConfigOptions($config);
    }

    /**
     * Config options relating to deleting data only.
     * @param DeleteOptions $deleteOptions
     */
    public function setDeleteOptions(DeleteOptions $deleteOptions): void
    {
        $this->options = $deleteOptions;
        $this->sqlDeleter->setDeleteOptions($deleteOptions);
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
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function setClassName(string $className): void
    {
        $mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->options->mappingCollection = $mappingCollection;
        $this->setDeleteOptions($this->options);
    }

    /**
     * @param object $entity
     * @param int $updateCount
     * @param int $deleteCount
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function checkForRemovals(object $entity, int &$updateCount, int &$deleteCount): void
    {
        $originalClass = $this->getClassName();
        $this->setClassName(ObjectHelper::getObjectClassName($entity));
        $children = $this->options->mappingCollection->getChildObjectProperties();
        foreach ($children as $childPropertyName) {
            if ($entity instanceof EntityProxyInterface && $entity->isChildAsleep($childPropertyName)) {
                continue; //Don't wake it up
            }
            $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($childPropertyName);
            $parentProperty = $childPropertyMapping->relationship->mappedBy;
            if ($childPropertyMapping->relationship->isToMany()
                && !$this->config->disableDeleteRelationships
                && $parentProperty
            ) {
                $childProperty = $childPropertyMapping->propertyName;
                $childClassName = $childPropertyMapping->getChildClassName();
                $childPks = $this->options->mappingCollection->getPrimaryKeyProperties($childClassName);
                
                $removedChildren = null;
                if ($this->entityTracker->hasEntity($entity)) {
                    $removedChildren = $this->entityTracker->getRemovedChildren($entity, $childProperty, $childPks);
                }
                if ($removedChildren === null) {
                    //Not tracked - have to try loading from database (if tracked but empty, we will have an empty array)
                    $removedChildren = $this->loadRemovedChildrenFromDatabase($entity, $childPropertyMapping, $childPks);
                }
                if ($removedChildren) {
                    $this->setDeleteOptions(DeleteOptions::create($this->options->mappingCollection));
                    $this->sendOrphanedKidsAway(
                        $childPropertyName,
                        $removedChildren,
                        $updateCount,
                        $deleteCount
                    );
                }
            }
        }
        
        $this->setClassName($originalClass);
    }

    /**
     * @param object $entity
     * @param DeleteOptions $deleteOptions
     * @param int $updateCount
     * @return int
     * @throws \ReflectionException|ObjectiphyException
     */
    public function deleteEntity(object $entity, DeleteOptions $deleteOptions, int &$updateCount): int
    {
        if ($this->config->disableDeleteEntities) {
            return 0;
        }
        $originalClass = $this->getClassName();
        $this->setClassName(ObjectHelper::getObjectClassName($entity));
        $deleteCount = 0;
        $this->setDeleteOptions($deleteOptions);
        $this->removeChildren($entity, $updateCount, $deleteCount);

        //Delete entity
        $qb = QB::create();
        $deleteQuery = $qb->buildDeleteQuery();
        $this->setClassName($originalClass);
        
        return $deleteCount + $this->executeDelete($deleteQuery, $this->options);
    }

    /**
     * @param DeleteQueryInterface $deleteQuery
     * @param DeleteOptions $options
     * @return int
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function executeDelete(DeleteQueryInterface $deleteQuery, DeleteOptions $options): int
    {
        $this->setDeleteOptions($options);
        $deleteCount = 0;
        $this->setClassName($deleteQuery->getDelete() ?: $this->getClassName());
        $deleteQuery->finalise($this->options->mappingCollection, $this->getClassName());
        $sql = $this->sqlDeleter->getDeleteSql($deleteQuery);
        $params = $this->sqlDeleter->getQueryParams();
        $this->explanation->addQuery($deleteQuery, $sql, $params, $this->options->mappingCollection, $this->config);
        if ($sql && $this->storage->executeQuery($sql, $params)) {
            $deleteCount = $this->storage->getAffectedRecordCount();
            $this->entityTracker->clear($this->getClassName());
        }

        return $deleteCount;
    }

    /**
     * @param iterable $entities
     * @param DeleteOptions $deleteOptions
     * @param int $updateCount
     * @return int
     * @throws ObjectiphyException|QueryException|\ReflectionException
     */
    public function deleteEntities(
        iterable $entities,
        DeleteOptions $deleteOptions,
        int &$updateCount
    ): int {
        if ($this->config->disableDeleteEntities) {
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
                $deleteCount += $this->executeDelete($deleteQuery->buildDeleteQuery(), $this->options);
            }
            $this->setClassName($originalClassName);
        }

        return $deleteCount;
    }

    /**
     * @param object $entity
     * @param PropertyMapping $childPropertyMapping
     * @param array $childPks
     * @return array
     * @throws ObjectiphyException|QueryException|\ReflectionException|\Throwable
     */
    private function loadRemovedChildrenFromDatabase(
        object $entity,
        PropertyMapping $childPropertyMapping,
        array $childPks
    ): array {
        $removedChildren = [];
        $parentProperty = $childPropertyMapping->relationship->mappedBy;
        if ($parentProperty) {
            //$delimitedPks = array_map(function($value){ return '%' . $value . '%'; }, $childPks);
            $originalClass = $this->getClassName();
            $this->setClassName($childPropertyMapping->getChildClassName());
            $query = QB::create()
                ->select(...$childPks)
                ->from($this->getClassName())
                ->where($parentProperty, QB::EQ, $entity)
                ->buildSelectQuery();

            //Need to set multiple to true on the find options
            $this->objectFetcher->setFindOptions(FindOptions::create($this->options->mappingCollection, ['multiple' => true]));
            $dbChildren = $this->objectFetcher->executeFind($query) ?: [];
            $entityChildren = ObjectHelper::getValueFromObject($entity, $childPropertyMapping->propertyName) ?: [];
            $removedChildren = $this->detectRemovals($dbChildren, $entityChildren, $childPks);
            $this->setClassName($originalClass);
        }

        return $removedChildren;
    }

    /**
     * @param iterable $dbChildren
     * @param iterable $entityChildren
     * @param array $childPks
     * @return array
     */
    private function detectRemovals(iterable $dbChildren, iterable $entityChildren, array $childPks): array
    {
        $removedChildren = [];
        foreach ($dbChildren ?? [] as $dbChild) {
            $childMatch = false;
            foreach ($entityChildren as $currentChild) {
                $pkMatch = true;
                foreach ($childPks as $pkProperty) {
                    $dbValue = ObjectHelper::getValueFromObject($dbChild, $pkProperty);
                    $currentValue = ObjectHelper::getValueFromObject($currentChild, $pkProperty);
                    if ($dbValue != $currentValue) {
                        $pkMatch = false;
                        break;
                    }
                }
                if ($pkMatch) {
                    $childMatch = true;
                    break;
                }
            }
            if (!$childMatch) {
                $removedChildren[] = $dbChild;
            }
        }

        return $removedChildren;
    }

    /**
     * @param object $entity
     * @param int $updateCount
     * @param int $deleteCount
     * @throws \ReflectionException|ObjectiphyException
     */
    private function removeChildren(object $entity, int &$updateCount, int &$deleteCount): void
    {
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
     * @param iterable $removedChildren
     * @param int $updateCount
     * @param int $deleteCount
     * @throws ObjectiphyException|\ReflectionException
     */
    public function sendOrphanedKidsAway(
        string $propertyName,
        iterable $removedChildren,
        int &$updateCount,
        int &$deleteCount
    ): void {
        $goingToOrphanage = [];
        $goingToBelize = [];
        $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($propertyName);
        foreach ($removedChildren as $removedChild) {
            if (!$this->config->disableDeleteEntities
                && !$this->options->disableCascade
                && ($childPropertyMapping->relationship->cascadeDeletes
                    || $childPropertyMapping->relationship->orphanRemoval)
            ) { //Nobody wants this baby
                $goingToBelize[] = $removedChild;
            } else { //Available for adoption
                $parentProperty = $childPropertyMapping->relationship->mappedBy;
                if ($parentProperty && !$this->config->disableDeleteRelationships) {
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

    /**
     * @param array $orphans
     * @param string $parentProperty
     * @param $updateCount
     * @throws ObjectiphyException|QueryException|\ReflectionException
     */
    private function sendKidsToOrphanage(array $orphans, string $parentProperty, &$updateCount): void
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
            $saveOptions = SaveOptions::create($this->objectMapper->getMappingCollectionForClass($childClass));
            $updateCount += $this->objectPersister->executeSave($query, $saveOptions);
        }
    }
}
