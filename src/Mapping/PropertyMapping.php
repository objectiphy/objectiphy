<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Mapping information for a particular property in context (ie. the same property
 * on different instances of the same class will have different context such as
 * aliases and relationship positioning).
 */
class PropertyMapping
{
    /**
     * @var string Class name of the entity this property belongs to
     */
    public string $className;

    public \ReflectionProperty $reflectionProperty;

    public string $propertyName;

    /**
     * @var array Indexed array of parent property names.
     */
    public array $parents = [];

    /**
     * @var MappingCollection Collection to which this mapping belongs
     */
    public MappingCollection $parentCollection;

    /**
     * @var Relationship If this property represents a relationship to a child entity, the relationship annotation.
     */
    public Relationship $relationship;

    /**
     * @var array Names of the properties on the target class for a join
     */
    public array $targetJoinProperties;

    /**
     * @var Table If the value of this property is stored in a column on the entity's table, the table
     * annotation.
     */
    public Table $table;

    /**
     * @var Table|null $childTable If this property represents a relationship to a child entity, the table annotation
     * for the child.
     */
    public ?Table $childTable;

    /**
     * @var Column If the value of this property is stored in a column on the entity's table, the column
     * annotation.
     */
    public Column $column;

    /**
     * @var bool Whether or not this is used in a join
     */
    public bool $isForeignKey = false;

    /**
     * @var bool Whether or not we can fetch this property in the current query
     */
    public bool $isFetchable = false;

    /**
     * @var string Locally cached fully qualified property path using dot notation.
     */
    private string $propertyPath = '';

    /**
     * @var string Locally cached alias for this property's value in the data set.
     */
    private string $alias = '';

    /**
     * @var string Locally cached alias for this property's column's table.
     */
    private string $tableAlias = '';

    /**
     * @var bool If children of this property are used in criteria, force it to join even if it wouldn't normally.
     */
    private bool $forcedEarlyBindingForJoin = false;

    public function __construct(
        string $className,
        \ReflectionProperty $reflectionProperty,
        Table $table,
        ?Table $childTable,
        Column $column,
        Relationship $relationship,
        array $parents = []
    ) {
        $this->className = $className;
        $this->reflectionProperty = $reflectionProperty;
        $this->propertyName = $reflectionProperty->getName();
        $this->table = $table;
        $this->childTable = $childTable;
        $this->column = $column;
        $this->relationship = $relationship;
        $this->parents = $parents;
        if ($this->relationship->sourceJoinColumn) {
            $this->isForeignKey = true;
        }
    }

    /**
     * Get the fully qualified property path using dot notation by default.
     * @param string $separator
     * @param bool $includingPropertyName
     * @return string
     */
    public function getPropertyPath($separator = '.', bool $includingPropertyName = true): string
    {
        if (!$this->propertyPath) {
            $this->propertyPath = implode('.', $this->parents);
        }
        $result = $separator == '.' ? $this->propertyPath : str_replace('.', $separator, $this->propertyPath);
        $result .= $includingPropertyName ? $separator . $this->propertyName : '';

        return ltrim($result, $separator);
    }

    public function getParentPath($separator = '.'): string
    {
        return $this->getPropertyPath($separator, false);
    }
    
    public function isScalarValue(): bool
    {
        return !$this->relationship->isDefined() || $this->relationship->isScalarJoin();
    }
    
    public function getChildClassName(): string
    {
        return $this->relationship->childClassName;
    }

    public function getRelationshipKey(): string
    {
        //If parent is embedded, use class name from parent as we might need multiple joins for different parents
        $className = $this->className;
        $parentProperty = $this->parentCollection->getPropertyMapping($this->getParentPath());
        if ($parentProperty && ($parentProperty->relationship->isEmbedded || $this->relationship->isScalarJoin())) {
            $className = $parentProperty->className . ':';
            //To enable same class on different properties, allow as far as grandparent
            $grandparent = $this->parentCollection->getPropertyMapping($parentProperty->getParentPath());
            $className .= $grandparent ? $grandparent->propertyName . ':' : '';
            $className .= $parentProperty->propertyName;
        }

        return $className . ':' . $this->propertyName;
    }
    
    /**
     * Try to use a nice alias with underscores. If there are clashes (due to property names that already contain
     * underscores), we have to get ugly and use an alternative separator that is never likely to appear in a property
     * name.
     * @return string
     */
    public function getAlias(): string
    {
        if (empty($this->alias)) {
            $this->alias = $this->getPropertyPath('_');
            if (array_key_exists($this->alias, $this->parentCollection->getColumns())) {
                $this->alias = $this->getPropertyPath('_-_');
            }
        }

        return $this->alias;
    }

    public function getTableAlias(bool $forJoin = false, $populate = true): string
    {
        if (!$populate) {
            return $this->tableAlias;
        }
        if (empty($this->tableAlias)
            && count($this->parents) > 0 //No need to alias scalar properties of main entity
            && (strpos($this->column->name, '.') === false || $this->relationship->isScalarJoin())) { //Already mapped to an alias manually, so don't mess
            //Embedded objects use the alias of their parent, anything else gets its own
            $parentPropertyMapping = $this->parentCollection->getPropertyMapping($this->getParentPath());
            if ($this->relationship->isScalarJoin()) { //Each property with a scalar join is a separate join so needs a unique alias
                $this->tableAlias = rtrim('obj_alias_' . $this->getParentPath('_'), '_') . '_' . $this->propertyName;
            } elseif ($parentPropertyMapping && $parentPropertyMapping->relationship->isEmbedded) {
                $this->tableAlias = $parentPropertyMapping->getTableAlias();
            } else {
                $this->tableAlias = rtrim('obj_alias_' . $this->getParentPath('_'), '_');
            }
            if ($this->relationship->isScalarJoin()) {
                $this->parentCollection->populateOtherMatchingScalarJoinTableAliases($this);
            }
        }
        $tableAlias = $this->tableAlias;
        
        if ($forJoin) {
            if (!$tableAlias && $this->relationship->childClassName && !$this->relationship->isScalarJoin()) {
                $tableAlias = 'obj_alias_' . $this->propertyName;
            } elseif (!$this->relationship->isScalarJoin()) {
                $tableAlias = ltrim($tableAlias . '_' . $this->propertyName, '_');
            }
        }

        return $tableAlias;
    }

    /**
     * @param string $alias Just used for scalar joins that share the same remote table.
     */
    public function setTableAlias(string $alias)
    {
        $this->tableAlias = $alias;
    }

    public function getFullColumnName(string $overrideTableAlias = ''): string
    {
        if ($this->column->aggregateFunctionName) {
            return ''; //Temporary measure until we support aggregates.
        }
        $table = $overrideTableAlias ?: $this->getTableAlias();
        $table = $table ?: $this->table->name;
        if ($this->relationship->isScalarJoin()) {
            $column = $this->getShortColumnName(false);
        } else {
            $column = $this->column->name;
        }

        return $column ? trim($table . '.' . $column, '.') : '';
    }

    public function isReadOnly(): bool
    {
        //Scalar joins are read only by default - everything else is writeable by default
        if ($this->column->isReadOnly === null) {
            return $this->relationship->isScalarJoin();
        }

        return $this->column->isReadOnly === false ? false : true;
    }

    public function getShortColumnName($useAlias = true, $columnName = null): string
    {
        $column = $useAlias ? $this->getAlias() : null;
        $column = $column ?? $columnName ?? $this->column->name;
        $lastDelimiter = strrpos($column, '.');
        if ($lastDelimiter !== false) {
            $column = substr($column, strrpos($column, '.') + 1);
        }

        return $column;
    }

    public function forceEarlyBindingForJoin(): void
    {
        $this->forcedEarlyBindingForJoin = true;
    }

    public function isWithinDepth(): bool
    {
        $maxDepth = $this->parentCollection->getMaxDepth();
        return !$this->relationship->isDefined()
            || !$maxDepth
            || $this->relationship->isEmbedded
            || $this->relationship->isScalarJoin()
            || count($this->parents ?? []) < $maxDepth;
    }

    /**
     * Check whether this property requires a late bound proxy (because we cannot fetch the properties of its child)
     * See also Relationship::isLateBound
     * @param bool $forJoin
     * @param array $row
     * @return bool
     */
    public function isLateBound(bool $forJoin = false, array $row = []): bool
    {
        if (!$forJoin && !$this->isWithinDepth()) {
            return true;
        }
        if ($this->parents &&
            $this->parentCollection->getPropertyMapping($this->getParentPath())->isLateBound($forJoin)) {
            return true;
        }
        if ($forJoin && $this->forcedEarlyBindingForJoin) {
            return false;
        } elseif ($this->relationship->isEmbedded || $this->relationship->isScalarJoin()) {
            return false;
        } elseif ($this->relationship->isLateBound()) {
            return true;
        } elseif (!$this->relationship->mappedBy && !$this->parentCollection->isPropertyFetchable($this)) {
            //If we have to lazy load to avoid recursion, it will be late bound - get pk only
            return true;
        } elseif (!empty($this->childTable) && $this->childTable->repositoryClassName && $this->childTable->alwaysLateBind) {
            return true;
        } elseif ($this->isForeignKey) {
            //If we don't have the child primary key, it will be late bound
            $pkProperties = $this->parentCollection->getPrimaryKeyProperties($this->getChildClassName()) ?? [];
            //TODO: Loop through all pk properties and ensure we have a value for all of them?
            $pkProperty = reset($pkProperties);
            if ($pkProperty) {
                $childPkPath = $this->getPropertyPath() . '.' . $pkProperty;
                $childPkPropertyMapping = $this->parentCollection->getPropertyMapping($childPkPath);
                if (!$childPkPropertyMapping || ($row && !isset($row[$childPkPropertyMapping->getShortColumnName()]))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return bool Whether or not we might need a lazy loader (used to decide whether to create a proxy or not)
     */
    public function mightBeLateBound(): bool
    {
        if ($this->relationship->isToOne() && $this->relationship->getEagerLoadToOne() === null) {
            return true; //Can't be sure at this stage, but always safe to use a proxy
        } else {
            return $this->isLateBound();
        }
    }

    public function isEager(bool $allowRecursion = false, bool $isLateBound = false): bool
    {
        if ($this->relationship->isEager($isLateBound || $this->isLateBound())) {
            //Only if we can get it without recursion
            return $allowRecursion || $this->parentCollection->isPropertyFetchable($this);
        }

        return false;
    }

    /**
     * @param array $entities
     * @return iterable
     * @throws MappingException
     */
    public function getCollection(array $entities): iterable
    {
        $collection = $entities;
        $collectionClass = $this->relationship->collectionClass;
        if (!$collectionClass || $collectionClass == 'array') {
            if ($this->reflectionProperty->hasType()) {
                $collectionClass = ObjectHelper::getTypeName($this->reflectionProperty->getType()); //Sometimes returns gibberish
                if ($collectionClass && (
                        !is_string($collectionClass)
                        || strlen($collectionClass) > 1000
                        || !class_exists($collectionClass)
                        || !is_a($collectionClass, '\Traversable', true
                    ))) {
                    $collectionClass = $this->getTypeHacky();
                }
            }
        }

        if ($collectionClass && $collectionClass != 'array') {
            $collectionFactoryClassName = $this->relationship->getCollectionFactoryClass();
            $collectionFactory = new $collectionFactoryClassName();
            $collection = $collectionFactory->createCollection($collectionClass, $entities);
        }

        return $collection;
    }

    /**
     * @return string
     * @throws MappingException
     */
    private function getTypeHacky(): string
    {
        //PHP ReflectionType seems buggy at times (on a Mac at least) - try a hacky way of checking the type
        try {
            $className = $this->className;
            $property = $this->propertyName;
            $hackyClass = new $className();
            $hackyClass->$property = 1; //Should cause an exception containing the actual type in the message
        } catch (\Throwable $ex) {
            if (strpos($ex->getMessage(), 'must be array') !== false) {
                return 'array';
            }
            $classStart = strpos($ex->getMessage(), 'must be an instance of ');
            if ($classStart !== false) {
                $classEnd = strpos($ex->getMessage(), ' or ') ?: (strpos($ex->getMessage(), ',') ?: strlen($ex->getMessage()));
                $length = $classEnd - ($classStart + 23);
                $className = substr($ex->getMessage(), $classStart + 23, $length);
                if (class_exists($className) && is_a($className, '\Traversable', true)) {
                    return $className;
                }
            }
            $errorMessage = 'Could not determine collection class for %1$s. Please try adding a collectionClass attribute to the Relationship mapping for this property.';
            throw new MappingException(sprintf($errorMessage, $this->className . '::' . $this->propertyName));
        }

        return '';
    }
    
    public function pointsToParent(): bool
    {
        $parentPropertyMapping = $this->parentCollection->getPropertyMapping($this->getParentPath());
        if ($parentPropertyMapping) {
            return $parentPropertyMapping->relationship->mappedBy == $this->propertyName;
        }

        return false;
    }

    public function getSourceJoinColumns(): array
    {
        $parentProperty = $this->parentCollection->getPropertyMapping($this->getParentPath());
        if ($parentProperty && $parentProperty->relationship->isEmbedded) {
            $table = $parentProperty->getTableAlias() ?: $parentProperty->table->name;
        } else {
            $table = $this->getTableAlias() ?: $this->table->name;
        }

        return $this->getJoinColumns($this->relationship->sourceJoinColumn, $table);
    }

    public function getTargetJoinColumns(): array
    {
        $table = $this->getTableAlias(true);
        $targetColumn = $this->relationship->targetJoinColumn;
        if ($this->relationship->isScalarJoin()) { //Just want the short column name
            $targetColumn = $this->getShortColumnName(false, $targetColumn);
        }
        return $this->getJoinColumns($targetColumn, $table);
    }
    
    private function getJoinColumns(string $sourceOrTargetColumn, string $table): array
    {
        $joinColumns = [];
        foreach (explode(',', $sourceOrTargetColumn) as $thisJoinColumn) {
            if (trim($thisJoinColumn)) {
                $joinColumn = '';
                if (strpos($thisJoinColumn, '.') === false) { //Table not specified in mapping definition
                    $joinColumn = $table;
                    $joinColumn = $joinColumn ?: $this->table->name; //No alias, so assume root
                    $joinColumn .= ".";
                }
                $joinColumn .= trim($thisJoinColumn);
                $joinColumns[] = $joinColumn;
            }
        }

        return $joinColumns;
    }
}
