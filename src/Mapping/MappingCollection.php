<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Orm\ConfigOptions;

/**
 * Represents the full mapping information for the entire object hierarchy of a given parent class.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class MappingCollection
{
    /**
     * @var string Name of parent entity class.
     */
    private string $entityClassName;

    /**
     * @var array Property mappings keyed by column name or alias (ie. as the data appears in the result array).
     */
    private array $columns = [];
    
    /** 
     * @var array Property mappings keyed by property path.
     */
    private array $properties = [];

    /**
     * @var array Relationship mappings keyed by parent property name and class 
     */
    private array $relationships = [];
    
    public function __construct(string $entityClassName)
    {
        $this->entityClassName = $entityClassName;
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getColumns()
    {
        return $this->columns;
    }
    
    public function addMapping(PropertyMapping $propertyMapping)
    {
        $propertyMapping->parentCollection = $this;
        $this->columns[$propertyMapping->getAlias()] = $propertyMapping;
        $this->properties[$propertyMapping->getPropertyPath()] = $propertyMapping;
        $relationshipKey = end($propertyMapping->parentProperties) . ':' . $propertyMapping->className;
        $this->relationships[$relationshipKey][] = $propertyMapping->propertyName;
    }

    public function getColumnForProperty(string $propertyName, array $parentProperties): ?string
    {
        $propertyPath = implode('.', array_merge($parentProperties, [$propertyName]));
        if (isset($this->properties[$propertyPath])) {
            return $this->properties[$propertyPath]->getShortColumn();
        }

        return null;
    }
    
    public function getPropertyForColumn(string $columnName): ?string
    {
        return $this->columns[$columnName] ?? null;
    }

    public function isRelationshipMapped(string $parentPropertyName, string $className, string $propertyName)
    {
        $relationships = $this->relationships[$parentPropertyName . ':' . $className] ?? [];
        return in_array($propertyName, $relationships);
    }
}
