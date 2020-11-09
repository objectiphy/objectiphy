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
        if (!$this->getEntityFromLocalCache($entityClassName, $entity)) { //TODO: Could be more efficient by doing this earlier
            $relationalPropertiesMapped = $this->bindRelationalProperties($entity, $row, $parents, $parentEntity);
            $propertiesMapped = $propertiesMapped ?: $relationalPropertiesMapped;
        }

        return $propertiesMapped ? $entity : null;
    }

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

    private function getEntityFromLocalCache(string $entityClassName, object &$entity): bool
    {
        $pkProperties = $this->mappingCollection->getPrimaryKeyProperties($entityClassName);
        $pkValues = [];
        foreach ($pkProperties as $pkProperty) {
            $pkValues[] = ObjectHelper::getValueFromObject($entity, $pkProperty);
        }

        if ($pkValues) {
            if ($this->entityTracker->hasEntity($entityClassName, $pkValues)) {
                $entity = $this->entityTracker->getEntity($entityClassName, $pkValues);
                return true;
            } else {
                $this->entityTracker->storeEntity($entity, $pkValues);
                return false;
            }
        } else {
            return false;
        }
    }

    private function bindScalarProperties(object $entity, array $row, array $parents): bool
    {
        $propertiesMapped = false;
        foreach ($this->mappingCollection->getPropertyMappings($parents) as $propertyMapping) {
            $valueFound = false;
            if ($propertyMapping->isScalarValue()) {
                if (array_key_exists($propertyMapping->getShortColumnName(), $row)) {
                    $value = $row[$propertyMapping->getShortColumnName()];
                    $this->applyValue($entity, $propertyMapping, $value);
                    $propertiesMapped = true;
                }
            }
        }

        return $propertiesMapped;
    }

    private function bindRelationalProperties(object $entity, array $row, array $parents, ?object $parentEntity): bool
    {
        foreach ($this->mappingCollection->getPropertyMappings($parents) as $propertyMapping) {
            $valueFound = false;
            $value = null;
            if ($propertyMapping->getChildClassName()) {
                if ($propertyMapping->pointsToParent()) {
                    $value = $parentEntity;
                    $valueFound = true;
                } elseif ($propertyMapping->isLateBound()) {
                    $closure = $this->createLateBoundClosure($propertyMapping, $row);
                    $valueFound = $closure instanceof \Closure;
                    if ($valueFound) {
                        $value = $propertyMapping->isEager() ? $closure() : $closure;
                    }
                } else {
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

    private function applyValue(object $entity, PropertyMapping $propertyMapping, $value)
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
        $closure = function() use ($mappingCollection, $configOptions, $propertyMapping, $row) {
            //Get the repository
            $result = null;
            $className = $propertyMapping->getChildClassName();
            $repositoryClassName = $propertyMapping->table->repositoryClassName;
            $repository = $this->repositoryFactory->createRepository($className, $repositoryClassName, $configOptions);

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
                        $qb->andWhere($whereProperty[$index], '=', $value)->build();
                    }
                }
                $criteria = $qb->build();
            }

            //Do the search
            if (isset($criteria)) {
                if ($propertyMapping->relationship->isToOne()) {
                    if ($usePrimaryKey) {
                        $result = $repository->find($criteria[0]->value);
                    } else {
                        $result = $repository->findOneBy($criteria);
                    }
                } else {
                    $orderBy = $propertyMapping->relationship->orderBy;
                    $result = $repository->findBy($criteria, $orderBy);
                    $result = $propertyMapping->getCollection($result);                    
                }
            }

            return $result;
        };

        return $closure;
    }
}
