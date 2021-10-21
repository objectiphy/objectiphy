<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Represents the full mapping information for the entire object hierarchy of a given parent class.
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
     * @var PropertyMapping[] Property mappings keyed by column name or alias (ie. as the data appears in the result 
     * array).
     */
    private array $columns = [];

    /**
     * @var PropertyMapping[] Property mappings keyed by property path.
     */
    private array $properties = [];

    /**
     * @var PropertyMapping[] Examples for each property of each class - NOT IN CONTEXT. Keyed by class name then 
     * property name - no property path, as this is just an example of each one, to allow us to access the short
     * column name without any aliases for use in custom joins.
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
     * @var bool[] Key = property path, value = whether we can fetch children of the given property
     */
    private array $fetchableProperties = [];

    /**
     * @var bool[] Key = property path, value = whether we can fetch children of the given property when
     * filtered according to serialization groups
     */
    private array $filteredFetchableProperties = [];

    /**
     * @var PropertyMapping[] Property mappings for scalar joins.
     */
    private array $scalarJoinProperties = [];

    /**
     * @var PropertyMapping[] Property mappings for relationships keyed by parent class and property name
     */
    private array $relationships = [];

    /**
     * @var bool[] Record which relationships have been processed, to prevent recursion
     */
    private array $mappedRelationships = [];

    /**
     * @var bool Whether relationship join information has been populated (we have to wait until all
     * relationships are added).
     */
    private bool $relationshipMappingDone = false;

    /**
     * @var bool Whether information about which columns to fetch has been populated (we have to wait until all
     * relationships are defined so that we know which joins are available).
     */
    private bool $columnMappingDone = false;

    /**
     * @var array Serialization groups that are currently in effect (hydration will be limited to properties that
     * belong to these).
     */
    private array $groups = [];

    /**
     * @var bool Whether or not ungrouped properties should be fetched
     */
    private bool $hydrateUngrouped = true;

    /**
     * @var int Maximum number of children that can be early bound
     */
    private int $maxDepth;

    public function __construct(string $entityClassName, int $maxDepth)
    {
        $this->entityClassName = $entityClassName;
        $this->maxDepth = $maxDepth;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Add the mapping information for a property to the collection and index it in various ways.
     * @param PropertyMapping $propertyMapping
     * @param bool $suppressFetch If the column is only being added for a join to filter criteria, we don't fetch it
     * (as any sibling properties will not be present and you would get a partially hydrated object).
     */
    public function addMapping(PropertyMapping $propertyMapping, bool $suppressFetch = false): void
    {
        $propertyMapping->parentCollection = $this;
        $this->properties[$propertyMapping->getPropertyPath()] ??= $propertyMapping;
        $parentPath = $propertyMapping->getParentPath();
        $this->propertiesByParent[$parentPath][$propertyMapping->propertyName] ??= $propertyMapping;
        $this->propertiesByClass[$propertyMapping->className][$propertyMapping->propertyName] ??= $propertyMapping;
        $this->classes[$propertyMapping->className] ??= $propertyMapping->table;
        if ($propertyMapping->childTable && $propertyMapping->getChildClassName()) {
            $this->classes[$propertyMapping->getChildClassName()] ??= $propertyMapping->childTable;
        }
        if ($propertyMapping->column->isPrimaryKey || $propertyMapping->relationship->isPrimaryKey) {
            $this->addPrimaryKeyMapping($propertyMapping->className, $propertyMapping->propertyName);
        }
        if ($propertyMapping->relationship->isDefined() && !$propertyMapping->relationship->isEmbedded) {
            $relationshipKey = $propertyMapping->getRelationshipKey();
            if (
                isset($this->relationships[$relationshipKey])
                && $propertyMapping->parents
                && $propertyMapping->parentCollection->hasAggregateFunctions($propertyMapping->className, $propertyMapping->propertyName)
            ) {
                //Another instance of this relationship already exists. Agg functions will fail if they appear more than once. Lazy load!
                $parentPropertyMapping = $this->getPropertyMapping($propertyMapping->getParentPath());
                $parentPropertyMapping->relationship->lazyLoad = true;
                $parentPropertyMapping->forceEarlyBindingForJoin();
            }
            $this->relationships[$relationshipKey] ??= $propertyMapping;
        }
        if ($propertyMapping->relationship->isScalarJoin()) {
            $this->scalarJoinProperties[] = $propertyMapping;
        }

        if ((!$suppressFetch && $propertyMapping->isWithinDepth())
            || $propertyMapping->isForeignKey
            || $propertyMapping->relationship->isEmbedded
        ) { //For now we will assume it is fetchable - if we have to late bind to avoid recursion, this can change
            $propertyMapping->isFetchable = true;
            $this->columns[$propertyMapping->getAlias()] ??= $propertyMapping;
            $this->fetchableProperties[$propertyMapping->getPropertyPath()] ??= $propertyMapping;
        }
    }

    public function addExtraTableMapping(string $className, Table $table)
    {
        $this->classes[$className] = $table;
    }

    public function setGroups(bool $hydrateUngrouped = true, string ...$groups)
    {
        $this->hydrateUngrouped = $hydrateUngrouped;
        $this->groups = $groups;
        $this->filteredFetchableProperties = [];
    }

    public function usesClass(string $className): bool
    {
        return isset($this->classes[$className]);
    }

    public function addPrimaryKeyMapping(string $className, string $propertyName): void
    {
        $this->primaryKeyProperties[$className][$propertyName] ??= 1;
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
     * Fetchable property mappings keyed by column alias
     * @return PropertyMapping[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Whether or not we can get any properties for the given property (that represents a relationship)
     * @param PropertyMapping $propertyMapping
     * @return bool
     */
    public function isPropertyFetchable(PropertyMapping $propertyMapping): bool
    {
        $value = $this->getFetchableProperties(false)[$propertyMapping->getPropertyPath()] ?? false;
        return $value ? true : false; //$value could be an object
    }

    public function forceFetchable(PropertyMapping $propertyMapping): void
    {
        $propertyMapping->isFetchable = true;
        $this->fetchableProperties[$propertyMapping->getPropertyPath()] = $propertyMapping;
    }

    public function getFetchableProperties(bool $finalise = true): array
    {
        if ($finalise && !$this->columnMappingDone) {
            $this->finaliseColumnMappings();
        }

        if ($this->groups) {
            $unfetchables = [];
            foreach ($this->fetchableProperties as $propertyMapping) {
                $propertyGroups = $propertyMapping->getGroups();
                if (!$propertyGroups && $this->hydrateUngrouped) {
                    continue;
                }
                if (!array_intersect($this->groups, $propertyGroups) && !$propertyMapping->isForcedFetch) {
                    $unfetchables[] = $propertyMapping;
                }
            }
            $fetchables = array_diff($this->fetchableProperties, $unfetchables);

            //Any children of unfetchable properties become unfetchable themselves
            foreach ($fetchables ?? [] as $index => $fetchable) {
                foreach ($unfetchables as $unfetchable) {
                    if (substr($fetchable->getPropertyPath(), 0, strlen($unfetchable->getPropertyPath()) + 1) == $unfetchable->getPropertyPath() . '.') {
                        $fetchables[$index] = null;
                        break;
                    }
                }
            }

            $this->filteredFetchableProperties = array_filter($fetchables);
        }

        return $this->groups ? $this->filteredFetchableProperties : $this->fetchableProperties;
    }

    /**
     * @param bool $finalise Whether or not to ensure relationships are complete (should be false only while
     * populating the mapping collection).
     * @return PropertyMapping[]
     * @throws MappingException
     */
    public function getRelationships(bool $finalise = true): array
    {
        if ($finalise && !$this->relationshipMappingDone) {
            $this->finaliseRelationshipMappings();
        }

        return $this->relationships;
    }

    /**
     * @param array|null $parents
     * @return array|PropertyMapping[]
     */
    public function getPropertyMappings(?array $parents = null): array
    {
        if ($parents === null) {
            return $this->properties;
        } else {
            $parentPropertyPath = implode('.', $parents);
            return $this->propertiesByParent[$parentPropertyPath] ?? [];
        }
    }

    /**
     * Given a property mapping, find a sibling that maps to the given column name, or given a class name,
     * just find the first property defined for that class with that column name (used to work out which properties 
     * to use for late bound joins).
     * @param string $columnName
     * @param PropertyMapping|null $siblingProperty
     * @param string $className
     * @param bool $exceptionIfNotFound
     * @return PropertyMapping|null
     * @throws MappingException
     */
    public function getPropertyByColumn(
        string $columnName,
        PropertyMapping $siblingProperty = null,
        string $className = '',
        bool $exceptionIfNotFound = true
    ): ?PropertyMapping {
        if ($siblingProperty) {
            $siblings = $this->getPropertyMappings($siblingProperty->parents);
            foreach ($siblings ?? [] as $sibling) {
                if ($sibling->column->name == $columnName) {
                    return $sibling;
                }
            }
        } elseif ($className) {
            foreach ($this->properties as $property) {
                if ($property->className == $className && $property->column->name == $columnName) {
                    return $property;
                }
            }
        }

        if ($exceptionIfNotFound) {
            $sourceProperty = isset($property) ? $property->className . '::' . $property->propertyName : 'Unknown';
            $targetProperty = isset($property) ? $property->getChildClassName() . '::' . $property->relationship->mappedBy : 'Unknown';
            $message = sprintf('The join between %1$s and %2$s cannot be late bound because there is no property on %3$s that maps to the join column `%4$s`. Please ensure you have defined a property for each column that is used in the join.',
                $sourceProperty,
                $targetProperty,
                isset($property) ? $property->className : 'Unknown',
                $columnName
            );
            throw new MappingException($message);
        }
        
        return null;
    }

    public function getPropertyMapping(string $propertyPath): ?PropertyMapping
    {
        $propertyPath = str_replace('%', '', $propertyPath);
        return $this->properties[$propertyPath] ?? null;
    }

    /**
     * Return example property mappings for the given class name. This is out of context so must only be used to obtain
     * generic information such as short column name.
     * @param string $className
     * @return PropertyMapping[]
     */
    public function getPropertyExamplesForClass(string $className): array
    {
        return $this->propertiesByClass[$className] ?? [];
    }

    /**
     * Return an example property mapping for the given class/property name. This is out of context so must only
     * be used to obtain generic information such as short column name.
     * @param string $className
     * @param string $propertyName
     * @return PropertyMapping|null
     */
    public function getPropertyExample(string $className, string $propertyName): ?PropertyMapping
    {
        return $this->propertiesByClass[$className][$propertyName] ?? null;
    }
    
    public function getChildObjectProperties(bool $ownedOnly = false, array $parents = []): array
    {
        $childProperties = [];
        $parentPath = implode('.', $parents);
        foreach ($this->propertiesByParent[$parentPath] ?? [] as $property) {
            if ($property->getChildClassName() && (!$ownedOnly || $property->getShortColumnName(false))) {
                $childProperties[] = $property->propertyName;
            }
        }

        return $childProperties;
    }
    
    public function getTableForClass(string $className): ?Table
    {
        return $this->classes[$className] ?? null;
    }
    
    public function getTables(): array 
    {
        return $this->classes;
    }

    /**
     * If there is more than one scalar join to the same table on an entity, it is only necessary to define the join
     * once, and we copy it to the others here.
     * @param PropertyMapping $sourcePropertyMapping
     */
    public function populateOtherMatchingScalarJoinTableAliases(PropertyMapping $sourcePropertyMapping)
    {
        foreach ($this->scalarJoinProperties as $propertyMapping) {
            if ($propertyMapping !== $sourcePropertyMapping
                && $propertyMapping->parents == $sourcePropertyMapping->parents
                && !$propertyMapping->getTableAlias(false, false)
                && $propertyMapping->relationship->joinTable == $sourcePropertyMapping->relationship->joinTable
                && $propertyMapping->relationship->sourceJoinColumn == $sourcePropertyMapping->relationship->sourceJoinColumn
                && $propertyMapping->relationship->targetJoinColumn == $sourcePropertyMapping->relationship->targetJoinColumn) {
                $propertyMapping->setTableAlias($sourcePropertyMapping->getTableAlias());
                //We don't need to join any more as we are re-using an existing join
                if (($key = array_search($propertyMapping, $this->relationships)) !== false) {
                    unset($this->relationships[$key]);
                }
            }
        }
    }

    /**
     * Return the column alias used for the given property
     * @param string $propertyPath
     * @param bool $fullyQualified
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

    public function markRelationshipMapped(string $propertyName, string $className): void
    {
        $this->mappedRelationships[$className . ':' . $propertyName] = true;
    } 
    
    public function isRelationshipAlreadyMapped(array $parents, string $propertyName, string $className): bool
    {
        if (isset($this->mappedRelationships[$className . ':' . $propertyName])) {
            return true;
        }

        return false;
    }

    /**
     * Return list of properties that are marked as being part of the primary key.
     * @param string|null $className Optionally specify a child class name (defaults to parent entity).
     * @return array
     */
    public function getPrimaryKeyProperties(?string $className = null): array
    {
        $className = $className ?? $this->entityClassName;
        $pkProperties = $this->primaryKeyProperties[$className] ?? [];
        if (!$pkProperties && property_exists($className, 'id')) { //If none specified, use 'id' if it exists
            $pkProperties = ['id' => true]; //Value doesn't matter
        }

        return array_keys($pkProperties);
    }

    /**
     * Return a list of primary key values for the given entity
     * @param object $entity
     * @return array
     */
    public function getPrimaryKeyValues(object $entity): array 
    {
        $pkValues = [];
        $className = ObjectHelper::getObjectClassName($entity);
        $pkProperties = $this->getPrimaryKeyProperties($className);
        foreach ($pkProperties as $pkProperty) {
            $pkValue = ObjectHelper::getValueFromObject($entity, $pkProperty);
            if ($pkValue !== null) {
                $pkValues[$pkProperty] = $pkValue;
            }
        }
        
        return $pkValues;
    }
    
    /**
     * Whether or not any of the properties in this collection use an aggregate function
     * @return bool
     */
    public function hasAggregateFunctions(
        string $className = '',
        string $collectionPropertyName = '',
        bool $fetchableOnly = false
    ): bool {
        foreach ($this->properties as $propertyMapping) {
            if ($propertyMapping->column->aggregateFunctionName
                && (!$fetchableOnly || $propertyMapping->isFetchable)
                && (
                    (!$className && !$collectionPropertyName)
                    || (
                        $propertyMapping->className == $className &&
                        $propertyMapping->column->aggregateCollectionPropertyName == $collectionPropertyName
                    )
                )
            ) {
                return true;
            }
        }
        
        return false;
    }

    public function parentHasLateBoundProperties(array $parents): bool
    {
        foreach ($this->getPropertyMappings($parents) as $propertyMapping) {
            if ($propertyMapping->mightBeLateBound()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove any non-fetchable columns from the column list (ie. columns that will need to be lazy loaded
     * even if the mapping doesn't specify that - to prevent recursion). We won't know which columns can be
     * eager fetched until all the relationship mapping is done.
     */
    private function finaliseColumnMappings(): void
    {
        $relationships = $this->getRelationships();

        //Check if we can get each column
        foreach ($this->columns as $columnAlias => $propertyMapping) {
            if ($propertyMapping->parents) {
                $parentPropertyMapping = $this->getPropertyMapping($propertyMapping->getParentPath());
                $propertyMapping->isFetchable = false;
                if ($propertyMapping->isWithinDepth()) {
                    if ($parentPropertyMapping && $parentPropertyMapping->relationship->isEmbedded) {
                        $propertyMapping->isFetchable = $parentPropertyMapping->isFetchable
                            && !$propertyMapping->relationship->isScalarJoin();
                    }
                    if (!$propertyMapping->isFetchable) {
                        foreach ($relationships as $relationship) {
                            if ($relationship->getPropertyPath() == $propertyMapping->getParentPath()
                                || (
                                    $relationship->relationship->isScalarJoin()
                                    && $relationship->getPropertyPath() == $propertyMapping->getPropertyPath()
                                )
                            ) {
                                $propertyMapping->isFetchable = !$relationship->isLateBound() || $propertyMapping->column->isPrimaryKey;
                                if ($propertyMapping->relationship->isScalarJoin()) {
                                    $propertyMapping->isFetchable = $parentPropertyMapping && $parentPropertyMapping->relationship->isEmbedded ? true : $propertyMapping->isFetchable;
                                }
                                break;
                            }
                        }
                    }
                }
                if (!$propertyMapping->isFetchable) {
                    unset($this->columns[$columnAlias]);
                    unset($this->fetchableProperties[$propertyMapping->getPropertyPath()]);
                }
            }
        }

        if (isset($columnAlias)) {
            $this->columnMappingDone = true;
        }
    }

    /**
     * Ensure joinTable, sourceJoinColumn, and targetJoinColumn are populated for
     * each relationship (ie. if not specified explicitly in the mapping information,
     * work it out).
     * @throws MappingException
     */
    private function finaliseRelationshipMappings(): void
    {
        foreach ($this->relationships ?? [] as $relationshipMapping) {
            $relationship = $relationshipMapping->relationship;
            if ($relationshipMapping->isWithinDepth() || ($relationshipMapping->relationship->mappedBy && !$relationshipMapping->column->name)) {
                if (!$relationship->joinTable) {
                    $relationship->joinTable = $this->classes[$relationship->childClassName]->name ?? '';
                }
                if (!$relationship->sourceJoinColumn && $relationship->mappedBy) {
                    //Get it from the other side...
                    $otherSidePropertyPath = $relationshipMapping->getPropertyPath() . '.' . $relationship->mappedBy;
                    $otherSideMapping = $this->getPropertyMapping($otherSidePropertyPath);
                    if ($otherSideMapping && $otherSideMapping->relationship) {
                        $relationship->sourceJoinColumn = $relationship->sourceJoinColumn ?: $otherSideMapping->relationship->targetJoinColumn;
                        //If empty, use primary key of $relationshipMapping's class
                        $relationship->sourceJoinColumn = $relationship->sourceJoinColumn ?: implode(
                            ',',
                            $this->getPrimaryKeyProperties(
                                $relationshipMapping->className
                            )
                        );
                        $relationship->targetJoinColumn = $relationship->targetJoinColumn ?: $otherSideMapping->relationship->sourceJoinColumn;
                        //If empty, use primary key of child class
                        $relationship->targetJoinColumn = $relationship->targetJoinColumn ?: implode(
                            ',',
                            $this->getPrimaryKeyProperties(
                                $relationship->childClassName
                            )
                        );
                        $relationship->joinTable = $relationship->joinTable ?: $otherSideMapping->getTableAlias();
                        $relationship->bridgeJoinTable = $relationship->bridgeJoinTable ?: $otherSideMapping->relationship->bridgeJoinTable;
                        $relationship->bridgeSourceJoinColumn = $relationship->bridgeSourceJoinColumn ?: $otherSideMapping->relationship->bridgeTargetJoinColumn;
                        $relationship->bridgeTargetJoinColumn = $relationship->bridgeTargetJoinColumn ?: $otherSideMapping->relationship->bridgeSourceJoinColumn;
                    }
                } elseif (!$relationship->targetJoinColumn) {
                    $pkPropertyNames = $this->getPrimaryKeyProperties($relationship->childClassName);
                    $relationship->targetJoinColumn = implode(',', $pkPropertyNames);
                }
                $relationship->validate($relationshipMapping);
            }
        }
        if (isset($relationship)) {
            $this->relationshipMappingDone = true;
        }
    }
}
