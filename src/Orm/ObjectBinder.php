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

    public function bindRowToEntity(array $row, string $entityClassName, ?object $parentEntity = null): object
    {
        if (!isset($this->mappingCollection)) {
            throw new MappingException('Mapping collection has not been supplied to the object binder.');
        }

        $requiresProxy = $this->mappingCollection->classHasLateBoundProperties($entityClassName);
        $entity = $this->entityFactory->createEntity($entityClassName, $requiresProxy);
        $this->bindScalarProperties($entityClassName, $entity, $row);
        if (!$this->getEntityFromLocalCache($entityClassName, $entity)) { //TODO: Could be more efficient by doing this earlier
            $this->bindRelationalProperties($entityClassName, $entity, $row, $parentEntity);
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

    private function getEntityFromLocalCache(string $entityClassName, object $entity): bool
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

    private function bindScalarProperties(string $entityClassName, object $entity, array $row): void
    {
        foreach ($this->mappingCollection->getPropertyMappings($entityClassName) as $propertyMapping) {
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

    private function bindRelationalProperties(string $entityClassName, object $entity, array $row, ?object $parentEntity): void
    {
        foreach ($this->mappingCollection->getPropertyMappings($entityClassName) as $propertyMapping) {
            $valueFound = false;
            $value = null;
            if ($propertyMapping->getChildClassName()) {
                if ($propertyMapping->pointsToParent()) {
                    $value = $parentEntity;
                } elseif ($propertyMapping->relationship->isLateBound()) {
                    $value = $this->createLateBoundClosure($propertyMapping, $row);
                } else {
                    $value = $this->bindRowToEntity($row, $propertyMapping->getChildClassName(), $entity);
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
        $closure = function() use ($propertyMapping, $row) {
            $result = null;
            $configOptions = $this->configOptions;
            $className = $propertyMapping->getChildClassName();
            $repositoryClassName = $propertyMapping->table->repositoryClassName;
            $repository = $this->repositoryFactory->createRepository($className, $repositoryClassName, $configOptions);

            if ($propertyMapping->relationship->mappedBy) {
                $whereProperty = $propertyMapping->relationship->mappedBy;
                $pkProperties = $this->mappingCollection->getPrimaryKeyProperties(false, $propertyMapping->className);
                $valueKey = reset($pkProperties)->getAlias(); //Not sure if we can do multiple join columns here...?
                $value = $row[$valueKey] ?? null;
                if ($value !== null) {
                    $criteria = QB::create()->where($whereProperty, '=', $value)->build();
                }
            }

            if (isset($criteria)) {
                if ($propertyMapping->relationship->isToOne()) {
                    $result = $repository->findOneBy($criteria);
                } else {
                    $orderBy = $propertyMapping->relationship->orderBy;
                    $result = $repository->findBy($criteria, $orderBy);
                    $result = $propertyMapping->relationship->getCollection($result);                    
                }
            }

            return $result;
        };

        return $propertyMapping->relationship->isEager() ? $closure() : $closure;
    }
}
