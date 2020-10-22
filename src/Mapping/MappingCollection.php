<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;

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

    /** @var Table Mapping for the main table. */
    private Table $table;

    /** @var array Table mapping for each class. */
    private array $classes = [];

    /**
     * @var PropertyMapping[] Property mappings keyed by column name or alias (ie. as the data appears in the result array).
     */
    private array $columns = [];

    /**
     * @var PropertyMapping[] Property mappings keyed by property path.
     */
    private array $properties = [];

    /**
     * @var PropertyMapping[] Property mappings for primary keys - indexed arrays of property mappings, keyed on class name, eg.
     * ['My\Class' => [0 => PropertyMapping, 1 => PropertyMapping], 'My\Child\Class' => [0 => PropertyMapping]
     */
    private array $primaryKeyProperties = [];

    /**
     * @var PropertyMapping[] Property mappings for relationships keyed by parent class and property name
     */
    private array $relationships = [];

    /**
     * @var bool Whether relationship join information has been populated (we have to
     * wait until all relationships are added).
     */
    private bool $relationshipMappingDone = false;

    public function __construct(string $entityClassName)
    {
        $this->entityClassName = $entityClassName;
    }

    public function setPrimaryTableMapping(Table $table)
    {
        $this->table = $table;
    }

    public function getPrimaryTableMapping()
    {
        return $this->table;
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getColumnDefinitions(): array
    {
        return $this->columns;
    }

    public function getRelationships(): array
    {
        if (!$this->relationshipMappingDone) {
            $this->finaliseRelationshipMappings();
        }

        return $this->relationships;
    }

    /**
     * @return PropertyMapping[]
     */
    public function getPropertyMappings(): array
    {
        return $this->properties;
    }

    public function getPropertyMapping(string $propertyPath): ?PropertyMapping
    {
        return $this->properties[$propertyPath] ?? null;
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
        $this->classes[$propertyMapping->className] = $propertyMapping->table;
        if ($propertyMapping->column->isPrimaryKey ?? false) {
            $this->primaryKeyProperties[$propertyMapping->className][$propertyMapping->propertyName] = $propertyMapping;
        }
        if ($propertyMapping->relationship->isDefined()) {
            $relationshipKey = $this->getRelationshipKey($propertyMapping);
            $this->relationships[$relationshipKey] = $propertyMapping;
        }
    }

    /**
     * Return the column alias used for the given property
     * @param string $propertyPath
     * @return string | null
     */
    public function getColumnForPropertyPath(string $propertyPath, bool $fullyQualified = false): ?string
    {
        $propertyMapping = $this->properties[$propertyPath] ?? null;
        if ($propertyMapping) {
            return $fullyQualified ? $propertyMapping->getFullColumnName() : $propertyMapping->getShortColumnName();
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
     * @param \Objectiphy\Objectiphy\Mapping\PropertyMapping $propertyMapping
     * @param bool $eagerLoadToOne
     * @param bool $eagerLoadToMany
     * @return bool
     */
    public function isRelationshipMapped(PropertyMapping $propertyMapping, bool $eagerLoadToOne, bool $eagerLoadToMany)
    {
        $result = false;
        $relationship = $propertyMapping->relationship;
        $parentProperty = end($propertyMapping->parentProperties);
        if ($relationship->childClassName ?? false && $relationship->isEager($eagerLoadToOne, $eagerLoadToMany)) {
            $relationshipKey = $this->getRelationshipKey($propertyMapping);
            $result = array_key_exists($relationshipKey, $this->relationships);
        }

        return $result;
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

    /**
     * Whether or not any of the properties in this collection use an aggregate function
     * @return bool
     */
    public function hasAggregateFunctions(): bool
    {
        foreach ($this->properties as $propertyMapping) {
            if ($propertyMapping->column->aggregateFunctionName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate a key describing the relationship to prevent infinite recursion
     */
    private function getRelationshipKey(PropertyMapping $propertyMapping)
    {
        $parentProperties = $propertyMapping->parentProperties ?? [''];
        $parentProperty = end($parentProperties);
        $relationshipKey = $parentProperty;
        $relationshipKey .= ':' . $propertyMapping->className;
        $relationshipKey .= ':' . $propertyMapping->propertyName;

        return $relationshipKey;
    }

    /**
     * Ensure joinTable, sourceJoinColumn, and targetJoinColumn are populated for
     * each relationship (ie. if not specified explicitly in the mapping information,
     * work it out).
     */
    private function finaliseRelationshipMappings()
    {
        /** @var PropertyMapping $relationshipMapping */
        foreach ($this->relationships as $relationshipMapping) {
            $relationship = $relationshipMapping->relationship;
            if (!$relationship->joinTable) {
                $relationship->joinTable = $this->classes[$relationship->childClassName]->name ?? '';
            }
            if (!$relationship->joinSql) {
                if (!$relationship->sourceJoinColumn && $relationship->mappedBy) {
                    //Get it from the other side...
                    $stop = true;
                } elseif (!$relationship->targetJoinColumn) {
                    $relationship->targetJoinColumn = implode(',',
                        $this->getPrimaryKeyProperties(
                            true,
                            $relationship->childClassName
                        )
                    );
                }
            }
        }
        $relationship->validate($relationshipMapping);
        $this->relationshipMappingDone = true;
    }
}
