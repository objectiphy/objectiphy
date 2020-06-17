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
     * @var array Property mappings for primary keys - indexed arrays of property mappings, keyed on class name, eg.
     * ['My\Class' => [0 => PropertyMapping, 1 => PropertyMapping], 'My\Child\Class' => [0 => PropertyMapping]
     */
    private array $primaryKeyProperties = [];

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

    /**
     * Add the mapping information for a property to the collection and index it by both column and property names.
     * @param PropertyMapping $propertyMapping
     */
    public function addMapping(PropertyMapping $propertyMapping)
    {
        $propertyMapping->parentCollection = $this;
        $this->columns[$propertyMapping->getAlias()] = $propertyMapping;
        $this->properties[$propertyMapping->getPropertyPath()] = $propertyMapping;
        if ($propertyMapping->column->isPrimaryKey ?? false) {
            $this->primaryKeyProperties[$propertyMapping->className][$propertyMapping->propertyName] = $propertyMapping;
        }
        if ($propertyMapping->relationship->isDefined()) {
            $relationshipKey = (end($propertyMapping->parentProperties) ?: '') . ':' . $propertyMapping->className;
            $this->relationships[$relationshipKey][] = $propertyMapping->propertyName;
        }
    }

    /**
     * Return the true, short, column name for this property (not an alias).
     * @param string $propertyName
     * @param array $parentProperties
     * @return string | null
     */
    public function getColumnForProperty(string $propertyName, array $parentProperties): ?string
    {
        $propertyPath = implode('.', array_merge($parentProperties, [$propertyName]));
        if (isset($this->properties[$propertyPath])) {
            return $this->properties[$propertyPath]->column->name;
        }

        return null;
    }

    /**
     * Return the property mapping that matches the given column alias
     * @param string $columnAlias
     * @return string
     */
    public function getPropertyForColumn(string $columnAlias): string
    {
        return $this->columns[$columnAlias] ?? '';
    }

    /**
     * Whether or not a relationship between two classes has already been added (prevents infinite recursion).
     * @param string $parentPropertyName
     * @param string $className
     * @param string $propertyName
     * @return bool
     */
    public function isRelationshipMapped(string $parentPropertyName, string $className, string $propertyName): bool
    {
        $relationships = $this->relationships[$parentPropertyName . ':' . $className] ?? [];
        return in_array($propertyName, $relationships);
    }

    /**
     * Return list of properties that are marked as being part of the primary key.
     * @param bool $namesOnly Whether or not to just return a list of property names as strings (defaults to returning
     * a list of PropertyMapping objects).
     * @param string $className Optionally specify a child class name (defaults to parent entity).
     * @return array
     */
    public function getPrimaryKeyProperties($namesOnly = false, ?string $className = null): array
    {
        $className = $className ?? $this->entityClassName;
        $pkProperties = $this->primaryKeyProperties[$className] ?? [];

        return $namesOnly ? array_keys($pkProperties) : $pkProperties;
    }
}
