<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\ConfigEntity;
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

    /**
     * @var array All the entities we have already created, keyed by class name, then
     * primary key value(s), eg. ['My\Entity' => ['1stKeyPart:2ndKeyPart' => $object]]
     */
    private array $boundObjects = [];
    
    public function __construct(RepositoryFactory $repositoryFactory, EntityFactory $entityFactory)
    {
        $this->repositoryFactory = $repositoryFactory;
        $this->entityFactory = $entityFactory;
    }

    public function setMappingCollection(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }
    
    public function setConfigOptions(ConfigOptions $configOptions)
    {
        $this->configOptions = $configOptions;
        foreach ($configOptions->getConfigOption('entityConfig') as $className => $configEntity) {
            if ($configEntity->entityFactory) {
                $this->entityFactory->registerCustomEntityFactory($className, $configEntity->entityFactory);
            }
        }
    }

    public function bindRowToEntity(array $row, string $entityClassName, array $parentProperties = [], ?object $parentEntity = null): object
    {
        if (!isset($this->mappingCollection)) {
            throw new MappingException('Mapping collection has not been supplied to the object binder.');
        }

        $requiresProxy = $this->mappingCollection->classHasLateBoundProperties($entityClassName, $parentProperties);
        $entity = $this->entityFactory->createEntity($entityClassName, $requiresProxy);
        $this->bindScalarProperties($entityClassName, $entity, $row, $parentProperties);
        if (!$this->getEntityFromLocalCache($entityClassName, $entity)) { //TODO: Could be more efficient by doing this earlier
            $this->bindRelationalProperties($entityClassName, $entity, $row, $parentProperties, $parentEntity);
        }

        return $entity;
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
        $pkProperties = $this->mappingCollection->getPrimaryKeyProperties(true, $entityClassName);
        $pkValues = [];
        foreach ($pkProperties as $pkProperty) {
            $pkValues[] = ObjectHelper::getValueFromObject($entity, $pkProperty);
        }
        $pkKey = implode(':', $pkValues);

        if ($pkKey && isset($this->boundObjects[$entityClassName][$pkKey])) {
            $entity = $this->boundObjects[$entityClassName][$pkKey];
            return true;
        } else {
            $this->boundObjects[$entityClassName][$pkKey] = $entity;
            return false;
        }
    }

    private function bindScalarProperties(string $entityClassName, object $entity, array $row, array $parentProperties): void
    {
        foreach ($this->mappingCollection->getPropertyMappings($entityClassName, $parentProperties) as $propertyMapping) {
            $valueFound = false;
            if ($propertyMapping->isScalarValue()) {
                $valueFound = isset($row[$propertyMapping->getShortColumnName()]);
                $value = $row[$propertyMapping->getShortColumnName()] ?? null;
                $type = $propertyMapping->column->type;
                $format = $propertyMapping->column->format;
            }
            if ($valueFound) {
                $name = $propertyMapping->propertyName;
                ObjectHelper::setValueOnObject($entity, $name, $value, $type, $format);
            }
        }
    }

    private function bindRelationalProperties(string $entityClassName, object $entity, array $row, array $parentProperties, ?object $parentEntity): void
    {
        foreach ($this->mappingCollection->getPropertyMappings($entityClassName, $parentProperties) as $propertyMapping) {
            $valueFound = false;
            $value = null;
            if ($propertyMapping->getChildClassName()) {
                if ($propertyMapping->pointsToParent()) {
                    $value = $parentEntity;
                } elseif ($propertyMapping->relationship->isLateBound()) {
                    $value = $this->createLateBoundClosure($propertyMapping, $row);
                } else {
                    $parentProperties = array_merge($propertyMapping->parentProperties, [$propertyMapping->propertyName]);
                    $value = $this->bindRowToEntity($row, $propertyMapping->getChildClassName(), $parentProperties, $entity);
                }
                $valueFound = $value ? true : false;
                $type = $propertyMapping->getChildClassName();
                $format = '';
            }
            if ($valueFound) {
                $name = $propertyMapping->propertyName;
                if ($entity instanceof EntityProxyInterface && $value instanceof \Closure) {
                    $entity->setLazyLoader($name, $value);
                } else {
                    ObjectHelper::setValueOnObject($entity, $name, $value, $type, $format);
                }
            }
        }
    }

    private function createLateBoundClosure(PropertyMapping $propertyMapping, array $row)
    {
        $mappingCollection = $this->mappingCollection;
        $configOptions = $this->configOptions;
        $closure = function() use ($mappingCollection, $configOptions, $propertyMapping, $row) {
            $result = null;
            $className = $propertyMapping->getChildClassName();
            $repositoryClassName = $propertyMapping->table->repositoryClassName;
            $repository = $this->repositoryFactory->createRepository($className, $repositoryClassName, $configOptions);

            if ($propertyMapping->relationship->mappedBy) {
                $whereProperty = $propertyMapping->relationship->mappedBy;

                $sourceJoinColumns = $propertyMapping->relationship->sourceJoinColumn ?? null;
                foreach (explode(',', $sourceJoinColumns) as $index => $sourceJoinColumn) {
                    $sibling = $mappingCollection->getSiblingPropertyByColumn($propertyMapping, trim($sourceJoinColumn));
                    $valueKey[$index] = $sibling->getAlias();
                }

//                if (!$valueKey) { //Use primary key (not sure if we will ever hit this?)
//                    $pkProperties = $mappingCollection->getPrimaryKeyProperties(false, $propertyMapping->className);
//                    foreach ($pkProperties ?? [] as $index => $pkProperty) {
//                        $valueKey[$index] = $pkProperty->getAlias();
//                    }
//                }

                $qb = QB::create();
                foreach ($valueKey as $alias) {
                    $value = $row[$alias] ?? null;
                    if ($value !== null) {
                        $qb->andWhere($whereProperty, '=', $value)->build();
                    }
                }
                $criteria = $qb->build();
                
            }

            if (isset($criteria)) {
                if ($propertyMapping->relationship->isToOne()) {
                    $result = $repository->findOneBy($criteria);
                } else {
                    $orderBy = $propertyMapping->relationship->orderBy;
                    $result = $repository->findBy($criteria, $orderBy);
                    $result = $propertyMapping->getCollection($result);                    
                }
            }

            return $result;
        };

        return $propertyMapping->relationship->isEager() ? $closure() : $closure;
    }
}
