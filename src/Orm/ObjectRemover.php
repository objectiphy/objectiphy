<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
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
    private EntityTracker $entityTracker;
    private DeleteOptions $options;
    private bool $disableDeleteRelationships = false;
    private bool $disableDeleteEntities = false;

    public function __construct(
        ObjectMapper $objectMapper,
        SqlDeleterInterface $sqlDeleter,
        StorageInterface $storage,
        EntityTracker $entityTracker
    ) {
        $this->objectMapper = $objectMapper;
        $this->sqlDeleter = $sqlDeleter;
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

    public function deleteEntity(object $entity, DeleteOptions $deleteOptions): int
    {
        if ($this->disableDeleteEntities) {
            return 0;
        }
        $this->setDeleteOptions($deleteOptions);

        if (!$deleteOptions->disableCascade) {
            //Cascade to children
            $this->deleteChildren($entity);
        }

        //Delete entity
        $qb = QB::create();
        $deleteQuery = $qb->buildDeleteQuery();

        return $this->executeDelete($deleteQuery);
    }

    public function executeDelete(DeleteQueryInterface $deleteQuery)
    {
        $deleteCount = 0;
        $className = $deleteQuery->getDelete() ?: $this->options->mappingCollection->getEntityClassName();
        $deleteQuery->finalise($this->options->mappingCollection, $className);
        $sql = $this->sqlDeleter->getDeleteSql($deleteQuery);
        $params = $this->sqlDeleter->getQueryParams();
        if ($this->storage->executeQuery($sql, $params)) {
            $deleteCount = $this->storage->getAffectedRecordCount();
            $this->entityTracker->clear($className);
        }

        return $deleteCount;
    }

    public function deleteEntities(iterable $entities, DeleteOptions $deleteOptions): int
    {
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
        }

        if ($deletes) {
            //Delete en-masse per class (but don't forget cascade!)
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

    private function deleteChildren(object $entity)
    {
        $children = $this->options->mappingCollection->getChildObjectProperties();
        foreach ($children as $childPropertyName) {
            $child = $entity->$childPropertyName ?? null;
            if (!empty($child)) {
                $childPropertyMapping = $this->options->mappingCollection->getPropertyMapping($childPropertyName);
                $childEntities = is_iterable($child) ? $child : [$child];
                if ($childPropertyMapping->relationship->cascadeDeletes) {
                    $this->deleteEntities($childEntities, $this->options);
                } elseif (!$this->disableDeleteRelationships && $childPropertyMapping->relationship->mappedBy) {
                    //RSW: set parent to null


                }
            }
        }
    }
}
