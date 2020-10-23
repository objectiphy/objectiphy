<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectBinder
{
    private EntityFactoryInterface $entityFactory;
    private MappingCollection $mappingCollection;
    
    /** @var ConfigEntity[] */
    private array $entityConfigOptions;
    
    public function __construct()
    {
        $this->entityFactory = new EntityFactory();
    }

    public function setMappingCollection(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }
    
    /**
     * These will already have been validated by the fetcher
     * @param ConfigEntity[] $entityConfigOptions
     */
    public function setEntityConfigOptions(array $entityConfigOptions)
    {
        $this->entityConfigOptions = $entityConfigOptions;
        foreach ($entityConfigOptions as $className => $configEntity) {
            if ($configEntity->entityFactory) {
                $this->entityFactory->registerCustomEntityFactory($className, $configEntity->entityFactory);
            }
        }
    }

    public function bindRowToEntity(array $row, string $entityClassName): object
    {
        if (!isset($this->mappingCollection)) {
            throw new MappingException('Mapping collection has not been supplied to the object binder.');
        }
        
        $entity = $this->entityFactory->createEntity($entityClassName);
        foreach ($this->mappingCollection->getPropertyMappings($entityClassName) as $propertyMapping) {
            $valueFound = false;
            if ($propertyMapping->isScalarValue()) {
                $valueFound = isset($row[$propertyMapping->getShortColumnName()]);
                $value = $row[$propertyMapping->getShortColumnName()] ?? null;
                $type = $propertyMapping->column->type;
                $format = $propertyMapping->column->format;
            } elseif ($propertyMapping->getChildClassName(true)) {
                $value = $this->bindRowToEntity($row, $propertyMapping->getChildClassName());
                $valueFound = $value ? true : false;
                $type = $propertyMapping->getChildClassName();
                $format = '';
            }
            if ($valueFound) {
                $name = $propertyMapping->propertyName;
                ObjectHelper::setValueOnObject($entity, $name, $value, $type, $format);
            }
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
}
