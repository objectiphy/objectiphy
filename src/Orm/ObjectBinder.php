<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Factory\EntityFactory;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectBinder
{
    private RepositoryFactory $repositoryFactory;
    private EntityFactory $entityFactory;
    private MappingCollection $mappingCollection;
    private ConfigOptions $configOptions;
    private EntityTracker $entityTracker;
    private DataTypeHandlerInterface $dataTypeHandler;
    
    public function __construct(
        RepositoryFactory $repositoryFactory, 
        EntityFactory $entityFactory,
        EntityTracker $entityTracker,
        DataTypeHandlerInterface $dataTypeHandler
    ) {
        $this->repositoryFactory = $repositoryFactory;
        $this->entityFactory = $entityFactory;
        $this->entityTracker = $entityTracker;
        $this->dataTypeHandler = $dataTypeHandler;
    }

    public function setMappingCollection(MappingCollection $mappingCollection): void
    {
        $this->mappingCollection = $mappingCollection;
    }
    
    public function setConfigOptions(ConfigOptions $configOptions): void
    {
        $this->configOptions = $configOptions;
        foreach ($configOptions->getConfigOption('entityConfig') as $className => $configEntity) {
            if ($configEntity->entityFactory) {
                $this->entityFactory->registerCustomEntityFactory($className, $configEntity->entityFactory);
                $this->entityTracker->clear($className);
            }
        }
    }

    /**
     * Take values from a flat array and hydrate an object hierarchy
     * @param array $row
     * @param string $entityClassName
     * @param array $parents
     * @param object|null $parentEntity
     * @return object|null
     * @throws MappingException
     * @throws \Throwable
     */
    public function bindRowToEntity(
        array $row,
        string $entityClassName,
        array $parents = [],
        ?object $parentEntity = null
    ): ?object {
        if (!isset($this->mappingCollection)) {
            throw new MappingException('Mapping collection has not been supplied to the object binder.');
        }
        $requiresProxy = $this->mappingCollection->parentHasLateBoundProperties($parents);
        $entity = $this->entityFactory->createEntity($entityClassName, $requiresProxy);
        $propertiesMapped = $this->bindScalarProperties($entity, $row, $parents);
        if ($propertiesMapped && !$this->getEntityFromLocalCache($entityClassName, $entity)) {
            $relationalPropertiesMapped = $this->bindRelationalProperties($entity, $row, $parents, $parentEntity);
            $propertiesMapped = $propertiesMapped ?: $relationalPropertiesMapped;
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

    /**
     * See if we can get it from the tracker.
     * @param string $entityClassName
     * @param object $entity
     * @return bool
     */
    private function getEntityFromLocalCache(string $entityClassName, object &$entity): bool
    {
        if (!$this->configOptions->bypassEntityCache) {
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
        
        return false;
    }

    /**
     * Loop through populating the scalar properties only.
     * @param object $entity
     * @param array $row
     * @param array $parents
     * @return bool
     */
    private function bindScalarProperties(object $entity, array $row, array $parents): bool
    {
        $propertiesMapped = false;
        foreach ($this->mappingCollection->getPropertyMappings($parents) as $propertyMapping) {
            $valueFound = false;
            if ($propertyMapping->isScalarValue()) {
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
     * @return bool
     * @throws MappingException
     * @throws \Throwable
     */
    private function bindRelationalProperties(object $entity, array $row, array $parents, ?object $parentEntity): bool
    {
        $valueFound = false;
        foreach ($this->mappingCollection->getPropertyMappings($parents) as $propertyMapping) {
            $value = null;
            if ($propertyMapping->getChildClassName()) {
                if ($propertyMapping->pointsToParent()) {
                    $value = $parentEntity;
                    $valueFound = true;
                } elseif ($propertyMapping->isLateBound(false, $row)) {
                    $closure = $this->createLateBoundClosure($propertyMapping, $row);
                    $valueFound = $closure instanceof \Closure;
                    if ($valueFound) {
                        $value = $propertyMapping->isEager() ? $closure() : $closure;
                    }
                }  else {
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
     * @throws \ReflectionException
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
        } else {
            if ($this->dataTypeHandler->toObjectValue($value, $type, $format)) {
                ObjectHelper::setValueOnObject($entity, $propertyMapping->propertyName, $value);
            }
        }
    }

    /**
     * This method is quite long because we only want to do this processing if we have to (ie. if the lazy load
     * is triggered), so it all needs to go in the lazy load closure.
     * @param PropertyMapping $propertyMapping
     * @param array $row
     * @return \Closure|iterable|null
     */
    private function createLateBoundClosure(PropertyMapping $propertyMapping, array $row)
    {
        $mappingCollection = $this->mappingCollection;
        $configOptions = $this->configOptions;
        //BypassEntityCache is used to ensure clones get refreshed from the database to detect changes
        $closure = function($bypassEntityCache = false) use ($mappingCollection, $configOptions, $propertyMapping, $row) {
            //Get the repository
            $result = null;
            $className = $propertyMapping->getChildClassName();
            $repositoryClassName = $propertyMapping->table->repositoryClassName;
            $repository = $this->repositoryFactory->createRepository(
                $className,
                $repositoryClassName,
                $configOptions,
                true
            );

            //Work out what to search for
            $usePrimaryKey = false;
            $relationshipMapping = $mappingCollection->getRelationships()[$propertyMapping->getRelationshipKey()];
            if ($relationshipMapping->relationship->mappedBy) { //Child owns the relationship
                $sourceJoinColumns = explode(',', $relationshipMapping->relationship->sourceJoinColumn) ?? [];
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
            $originalBypassCache = $repository->setConfigOption('bypassEntityCache', $bypassEntityCache);
            if ($query && $query->getWhere()) {
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
            $repository->setConfigOption('bypassEntityCache', $originalBypassCache);

            return $result;
        };

        return $closure;
    }
}
