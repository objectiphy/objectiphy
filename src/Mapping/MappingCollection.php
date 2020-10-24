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
     * @var PropertyMapping[] Property mappings keyed by class name then property path.
     */
    private array $propertiesByClass = [];

    /**
     * @var PropertyMapping[] Property mappings keyed by parent property path then property name.
     */
    private array $propertiesByParent = [];

    /**
     * @var PropertyMapping[] Property mappings for primary keys - indexed arrays of property mappings, keyed on
     * class name, eg.
     * ['My\Class' => [0 => PropertyMapping, 1 => PropertyMapping], 'My\Child\Class' => [0 => PropertyMapping]
     */
    private array $primaryKeyProperties = [];

    /**
     * @var PropertyMapping[] Property mappings for relationships keyed by parent class and property name
     */
    private array $relationships = [];

    /**
     * @var bool Whether relationship join information has been populated (we have to wait until all
     * relationships are added).
     */
    private bool $relationshipMappingDone = false;

    public function __construct(string $entityClassName)
    {
        $this->entityClassName = $entityClassName;
    }

    public function setPrimaryTableMapping(Table $table): void
    {
        $this->table = $table;
    }

    public function getPrimaryTableMapping(): Table
    {
        return $this->table;
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    /**
     * @return PropertyMapping[]
     */
    public function getColumnDefinitions(): array
    {
        return $this->columns;
    }

    /**
     * @return PropertyMapping[]
     */
    public function getRelationships(): array
    {
        if (!$this->relationshipMappingDone) {
            $this->finaliseRelationshipMappings();
        }

        return $this->relationships;
    }

    /**
     * @param string $forClass Optionally filter by class name.
     * @return PropertyMapping[]
     */
    public function getPropertyMappings(string $forClass = ''): array
    {
        if ($forClass) {
            return $this->propertiesByClass[$forClass] ?? [];
        } else {
            return $this->properties;
        }
    }

    public function getPropertyMapping(string $propertyPath): ?PropertyMapping
    {
        return $this->properties[$propertyPath] ?? null;
    }
    
    /**
     * Add the mapping information for a property to the collection and index it in various ways.
     * @param PropertyMapping $propertyMapping
     */
    public function addMapping(PropertyMapping $propertyMapping)
    {
        $propertyMapping->parentCollection = $this;
        $this->columns[$propertyMapping->getAlias()] = $propertyMapping;
        $this->properties[$propertyMapping->getPropertyPath()] = $propertyMapping;
        $this->classes[$propertyMapping->className] = $propertyMapping->table;
        if ($propertyMapping->childTable && $propertyMapping->relationship->childClassName) {
            $this->classes[$propertyMapping->relationship->childClassName] = $propertyMapping->childTable;
        }
        $this->propertiesByClass[$propertyMapping->className][$propertyMapping->getPropertyPath()] = $propertyMapping;
        $parentPropertyPath = implode('.', $propertyMapping->parentProperties);
        $this->propertiesByParent[$parentPropertyPath][$propertyMapping->propertyName] = $propertyMapping;
        if ($propertyMapping->column->isPrimaryKey ?? false) {
            $this->primaryKeyProperties[$propertyMapping->className][$propertyMapping->propertyName] = $propertyMapping;
        }
        if ($propertyMapping->relationship->isDefined()) {
            $this->relationships[] = $propertyMapping;
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

    public function isRelationshipAlreadyMapped(array $parentProperties, string $propertyName): bool
    {
        $propertyPath = implode('.', array_merge($parentProperties, [$propertyName]));
        if (($this->propertiesByParent[$propertyPath] ?? false)) {
            return true;
        }

        return false;
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

    public function classHasLateBoundProperties(string $className): bool
    {
        foreach ($this->getPropertyMappings($className) as $propertyMapping) {
            if ($propertyMapping->relationship->isLateBound()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure joinTable, sourceJoinColumn, and targetJoinColumn are populated for
     * each relationship (ie. if not specified explicitly in the mapping information,
     * work it out).
     */
    private function finaliseRelationshipMappings(): void
    {
        foreach ($this->relationships ?? [] as $relationshipMapping) {
            $relationship = $relationshipMapping->relationship;
            if (!$relationship->joinTable) {
                $relationship->joinTable = $this->classes[$relationship->childClassName]->name ?? '';
            }
            if (!$relationship->joinSql) {
                if (!$relationship->sourceJoinColumn && $relationship->mappedBy) {
                    //Get it from the other side...
                    $otherSidePropertyPath = $relationshipMapping->getPropertyPath() . '.' . $relationship->mappedBy;
                    $otherSideMapping = $this->getPropertyMapping($otherSidePropertyPath);
                    if ($otherSideMapping && $otherSideMapping->relationship) {
                        $relationship->sourceJoinColumn = $relationship->sourceJoinColumn ?: $otherSideMapping->relationship->targetJoinColumn;
                        $relationship->targetJoinColumn = $relationship->targetJoinColumn ?: $otherSideMapping->relationship->sourceJoinColumn;
                        $relationship->joinTable = $relationship->joinTable ?: $otherSideMapping->getTableAlias();
                    }
                } elseif (!$relationship->targetJoinColumn) {
                    $pkPropertyNames = $this->getPrimaryKeyProperties(true, $relationship->childClassName);
                    $relationship->targetJoinColumn = implode(',', $pkPropertyNames);
                }
            }
            $relationship->validate($relationshipMapping);
        }
        if (isset($relationship)) {
            $this->relationshipMappingDone = true;
        }
    }
}
