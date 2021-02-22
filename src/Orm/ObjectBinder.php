<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectBinder
{
    private RepositoryFactory $repositoryFactory;
    private EntityFactoryInterface $entityFactory;
    private MappingCollection $mappingCollection;
    private ConfigOptions $configOptions;
    private EntityTracker $entityTracker;
    private DataTypeHandlerInterface $dataTypeHandler;
    private array $knownValues = [];

    public function __construct(
        RepositoryFactory $repositoryFactory, 
        EntityFactoryInterface $entityFactory,
        EntityTracker $entityTracker,
        DataTypeHandlerInterface $dataTypeHandler
    ) {
        $this->repositoryFactory = $repositoryFactory;
        $this->entityFactory = $entityFactory;
        $this->entityTracker = $entityTracker;
        $this->dataTypeHandler = $dataTypeHandler;
    }

    /**
     * @param MappingCollection $mappingCollection
     */
    public function setMappingCollection(MappingCollection $mappingCollection): void
    {
        $this->mappingCollection = $mappingCollection;
    }

    /**
     * @param ConfigOptions $configOptions
     * @throws ObjectiphyException
     */
    public function setConfigOptions(ConfigOptions $configOptions): void
    {
        $this->configOptions = $configOptions;
        foreach ($configOptions->getConfigOption('entityConfig') as $className => $configEntity) {
            $customEntityFactory = $configEntity->getConfigOption('entityFactory');
            if ($customEntityFactory) {
                $this->entityFactory->registerCustomEntityFactory($className, $customEntityFactory);
                $this->entityTracker->clear($className);
            }
        }
    }

    /**
     * No need to bind these as we already know the values (typically used to pre-populate the parent)
     * @param array $knownValues
     */
    public function setKnownValues(array $knownValues)
    {
        $this->knownValues = $knownValues;
    }

    /**
     * Take values from a flat array and hydrate an object hierarchy
     * @param array $row
     * @param string $entityClassName
     * @param array $parents
     * @param object|null $parentEntity
     * @param bool $useLateBinding If false, lazy-loaded and to-many relationships will return null
     * (this is used by IterableResult to prevent MySQL crashes trying to run a query with an already
     * open connection).
     * @return object|null
     * @throws MappingException
     * @throws \Throwable
     */
    public function bindRowToEntity(
        array $row,
        string $entityClassName,
        array $parents = [],
        ?object $parentEntity = null,
        bool $useLateBinding = true
    ): ?object {
        if (!isset($this->mappingCollection)) {
            throw new MappingException('Mapping collection has not been supplied to the object binder.');
        }
        $requiresProxy = $this->mappingCollection->parentHasLateBoundProperties($parents);
        $entity = $this->entityFactory->createEntity($entityClassName, $requiresProxy);
        foreach ($this->knownValues as $property => $value) {
            ObjectHelper::setValueOnObject($entity, $property, $value);
        }
        $propertiesMapped = $this->bindScalarProperties($entity, $row, $parents);
        if ($propertiesMapped && !$this->getEntityFromLocalCache($entityClassName, $entity)) {
            $this->bindRelationalProperties($entity, $row, $parents, $parentEntity, $useLateBinding);
            $this->entityTracker->storeEntity($entity, $this->mappingCollection->getPrimaryKeyValues($entity));
        }

        return $propertiesMapped ? $entity : null;
    }

    /**
     * Loop through the records, creating an array of objects.
     * @param array $rows
     * @param string $entityClassName
     * @param string $keyProperty
     * @return array
     * @throws MappingException
     * @throws \Throwable
     */
    public function bindRowsToEntities(array $rows, string $entityClassName, string $keyProperty): array
    {
        $entities = [];
        foreach ($rows as $row) {
            $entity = $this->bindRowToEntity($row, $entityClassName);
            $key = count($entities);
            if ($keyProperty) {
                $keyValueColumn = $this->mappingCollection->getColumnForPropertyPath($keyProperty);
                $key = $row[$keyValueColumn] ?? $key;
            }
            $entities[$key] = $entity;
        }
        
        return $entities;
    }

    public function clearMappingCache(?string $className = null)
    {
        $this->repositoryFactory->getObjectMapper()->clearMappingCache($className);
    }

    /**
     * See if we can get it from the tracker.
     * @param string $entityClassName
     * @param object $entity
     * @return bool
     */
    private function getEntityFromLocalCache(string $entityClassName, object &$entity): bool
    {
        $pkValues = $this->mappingCollection->getPrimaryKeyValues($entity);
        if ($pkValues) {
            if ($this->entityTracker->hasEntity($pkValues ? $entityClassName : $entity, $pkValues)) {
                $entity = $this->entityTracker->getEntity($entityClassName, $pkValues);
                return true;
            }
        }
        //We store it now to prevent recursion, then update when fully hydrated.
        $this->entityTracker->storeEntity($entity, $pkValues);

        return false;
    }

    /**
     * Loop through populating the scalar properties only.
     * @param object $entity
     * @param array $row
     * @param array $parents
     * @return bool
     * @throws \ReflectionException
     */
    private function bindScalarProperties(object $entity, array $row, array $parents): bool
    {
        $propertiesMapped = false;
        foreach ($this->mappingCollection->getPropertyMappings($parents) as $propertyMapping) {
            $valueFound = isset($this->knownValues[$propertyMapping->propertyName]);
            if (!$valueFound && $propertyMapping->isScalarValue()) {
                if (array_key_exists($propertyMapping->getShortColumnName(), $row)) {
                    $value = $row[$propertyMapping->getShortColumnName()]; //Prioritises alias, falls back to short column
                    $this->applyValue($entity, $propertyMapping, $value);
                    $propertiesMapped = $propertiesMapped || $value !== null;
                }
            }
        }

        return $propertiesMapped;
    }

    /**
     * Loop through the relationships.
     * @param object $entity
     * @param array $row
     * @param array $parents
     * @param object|null $parentEntity
     * @param bool $useLateBinding
     * @return bool
     * @throws MappingException
     * @throws \Throwable
     */
    private function bindRelationalProperties(
        object $entity,
        array $row,
        array $parents,
        ?object $parentEntity,
        bool $useLateBinding = true
    ): bool {
        $valueFound = false;
        foreach ($this->mappingCollection->getPropertyMappings($parents) as $propertyMapping) {
            $value = null;
            if ($propertyMapping->getChildClassName()) {
                $valueFound = boolval($value = $this->knownValues[$propertyMapping->propertyName] ?? null);
                if (!$valueFound && $propertyMapping->pointsToParent()) {
                    $value = $parentEntity;
                    $valueFound = true;
                } elseif (!$valueFound && $propertyMapping->isLateBound(false, $row)) {
                    $knownValues = [];
                    if ($propertyMapping->relationship->mappedBy) {
                        $knownValues[$propertyMapping->relationship->mappedBy] = $entity;
                    } elseif (!$propertyMapping->relationship->mappedBy) {
                        $childProperties = $this->mappingCollection->getPropertyExamplesForClass($propertyMapping->getChildClassName());
                        foreach ($childProperties as $childProperty) {
                            if ($childProperty->relationship->isToOne() && $childProperty->relationship->mappedBy == $propertyMapping->propertyName) {
                                $knownValues[$childProperty->propertyName] = $entity;
                                break;
                            }
                        }
                    }
                    if ($useLateBinding) {
                        $closure = $this->createLateBoundClosure($propertyMapping, $row, $knownValues);
                        $valueFound = $closure instanceof \Closure;
                        if ($valueFound) {
                            $value = $propertyMapping->isEager(true, true) ? $closure() : $closure;
                        }
                    } else {
                        $value = null;
                        $valueFound = true;
                    }
                } elseif (!$valueFound) {
                    $parents = array_merge($propertyMapping->parents, [$propertyMapping->propertyName]);
                    $childClass = $propertyMapping->getChildClassName();
                    $value = $this->bindRowToEntity($row, $childClass, $parents, $entity);
                    $valueFound = $value ? true : false;
                }
                if ($valueFound) {
                    $this->applyValue($entity, $propertyMapping, $value);
                }
            }
        }

        return $valueFound;
    }

    /**
     * Convert persistence value to object value.
     * @param object $entity
     * @param PropertyMapping $propertyMapping
     * @param $value
     */
    private function applyValue(object $entity, PropertyMapping $propertyMapping, $value): void
    {
        if ($propertyMapping->getChildClassName()) {
            $type = $propertyMapping->relationship->isToOne() ? $propertyMapping->getChildClassName() : '\iterable';
            $format = ''; //Not applicable to child objects
        } else {
            $type = $propertyMapping->column->type;
            $format = $propertyMapping->column->format;
        }

        if ($entity instanceof EntityProxyInterface && $value instanceof \Closure) {
            $entity->setLazyLoader($propertyMapping->propertyName, $value);
        } elseif ($this->dataTypeHandler->toObjectValue($value, $type, $format)) {
            ObjectHelper::setValueOnObject($entity, $propertyMapping->propertyName, $value);
        }
    }

    /**
     * This method is quite long because we only want to do this processing if we have to (ie. if the lazy load
     * is triggered), so it all needs to go in the lazy load closure.
     * @param PropertyMapping $propertyMapping
     * @param array $row
     * @param array $knownValues
     * @return \Closure|iterable|null
     */
    private function createLateBoundClosure(PropertyMapping $propertyMapping, array $row, array $knownValues = [])
    {
        $mappingCollection = $this->mappingCollection;
        $configOptions = clone($this->configOptions);
        return function() use ($mappingCollection, $configOptions, $propertyMapping, $row, $knownValues) {
            //Get the repository
            $result = null;
            $className = $propertyMapping->getChildClassName();
            $repositoryClassName = $propertyMapping->childTable->repositoryClassName;
            $repository = $this->repositoryFactory->createRepository(
                $className,
                $repositoryClassName,
                $configOptions,
                true
            );

            //We have to allow the cache, otherwise we get recursion - cache will have been cleared before the parent was fetched
            $repository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, false);

            //Work out what to search for
            $usePrimaryKey = false;
            //Relationship used by mapping collection might differ from $propertyMapping
            $relationshipMapping = $mappingCollection->getRelationships()[$propertyMapping->getRelationshipKey()];
            if ($relationshipMapping->relationship->mappedBy) { //Child owns the relationship
                $sourceJoinColumns = explode(',', $relationshipMapping->relationship->sourceJoinColumn) ?? [];
                if (!array_filter($sourceJoinColumns)) {
                    $message = sprintf('Could not determine source join column for relationship %1$s::%2$s', $relationshipMapping->className, $relationshipMapping->propertyName);
                    throw new MappingException($message);
                }
                foreach ($sourceJoinColumns as $index => $sourceJoinColumn) {
                    $sibling = $mappingCollection->getPropertyByColumn(
                        trim($sourceJoinColumn),
                        $propertyMapping
                    );
                    $whereProperty[$index] = $relationshipMapping->relationship->mappedBy;
                    if (count($sourceJoinColumns) > 1) {
                        $whereProperty[$index] .= '.' . $sibling->propertyName;
                    }
                    $valueKey[$index] = $sibling->getAlias();
                }
            } else {
                if ($relationshipMapping->relationship->getTargetProperty()) { //Not joining to single primary key
                    $sourceJoinColumns = explode(',', $relationshipMapping->relationship->sourceJoinColumn) ?? [];
                    $targetProperties = explode(',', $relationshipMapping->relationship->getTargetProperty()) ?? [];
                    foreach ($targetProperties as $index => $targetProperty) {
                        $sourceProperty = $mappingCollection->getPropertyByColumn(
                            $sourceJoinColumns[$index],
                            $propertyMapping
                        );
                        $whereProperty[$index] = trim($targetProperty);
                        $valueKey[$index] = $sourceProperty->getAlias();
                    }
                }
                if (empty($whereProperty) || empty($valueKey)) { //Single primary key
                    $pkProperties = $mappingCollection->getPrimaryKeyProperties($propertyMapping->getChildClassName());
                    $firstPk = reset($pkProperties);
                    if ($firstPk) {
                        $whereProperty[0] = $firstPk;
                        $valueKey[0] = $propertyMapping->getAlias();
                        $usePrimaryKey = true;
                    }
                }
            }

            //Build the criteria to search for
            if (!empty($whereProperty) && !empty($valueKey)) {
                $qb = QB::create();
                foreach ($valueKey as $index => $alias) {
                    $value = $row[$alias] ?? null;
                    if ($value !== null) {
                        $qb->and($whereProperty[$index], '=', $value);
                    }
                }
                $query = $qb->buildSelectQuery();
            }

            //Do the search
            if (!empty($query) && $query->getWhere()) {
                // In case the joinTable is different to the one on the entity (overridden, or just a cloned table for
                // a different use case on this relationship), specify the table instead of looking it up via the class
                // name (so it works in the same as a join would have)
                $joinTable = $propertyMapping->relationship->joinTable ?? '';
                if ($joinTable && ($mappingCollection->getTableForClass($className)->name ?? '') != $joinTable) {
                    $joinTable = '`' . str_replace('.', '`.`', $propertyMapping->relationship->joinTable) . '`';
                    $query->setClassName($joinTable);
                    $usePrimaryKey = false;
                }
                $repository->setKnownValues($knownValues);
                if ($propertyMapping->relationship->isToOne()) {
                    if ($usePrimaryKey) {
                        $result = $repository->find($query->getWhere()[0]->value);
                    } else {
                        $result = $repository->findOneBy($query);
                    }
                } else {
                    $orderBy = $propertyMapping->relationship->orderBy;
                    $result = $repository->findBy($query, $orderBy);
                    $result = $propertyMapping->getCollection($result);                    
                }
            }

            return $result;
        };
    }
}
