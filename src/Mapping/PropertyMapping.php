<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Exception\MappingException;

/**
 * Mapping information for a particular property in context (ie. the same property
 * on different instances of the same class will have different context such as
 * aliases and relationship positioning).
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
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
     * @var Table If this property represents a relationship to a child entity, the table annotation for the child.
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

    public function getParentPath($separator = '.')
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

    public function getRelationshipKey()
    {
        return $this->className . ':' . $this->propertyName;
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
            if (array_key_exists($this->alias, $this->parentCollection->getColumns(false))) {
                $this->alias = $this->getPropertyPath('_-_');
            }
        }

        return $this->alias;
    }

    public function getTableAlias(bool $forJoin = false): string
    {
        if (empty($this->tableAlias)
            && count($this->parents) > 0 //No need to alias scalar properties of main entity
            && strpos($this->column->name, '.') === false) { //Already mapped to an alias manually, so don't mess 
            $this->tableAlias = rtrim('obj_alias_' . implode('_', $this->parents), '_');
        }
        $tableAlias = $this->tableAlias;
        
        if ($forJoin) {
            if (!$tableAlias && $this->relationship->childClassName) {
                $tableAlias = 'obj_alias_' . $this->propertyName;
            } else {
                $tableAlias = ltrim($tableAlias . '_' . $this->propertyName, '_');
            }
        }

        return $tableAlias;
    }

    public function getFullColumnName(): string
    {
        if ($this->relationship->isEmbedded) {
            return ''; //Temporary measure until we support embedables.
        } elseif ($this->column->aggregateFunctionName) {
            return ''; //Temporary measure until we support aggregates.
        } 
        $table = $this->getTableAlias();
        $table = $table ?: $this->table->name;
        $column = $this->column->name;

        return $column ? trim($table . '.' . $column, '.') : '';
    }

    public function getShortColumnName($useAlias = true): string
    {
        $columnName = $useAlias ? $this->getAlias() : '';
        $columnName = $columnName ?: $this->column->name;

        return $columnName;
    }

    public function forceEarlyBindingForJoin()
    {
        $this->forcedEarlyBindingForJoin = true;
    }

    public function isLateBound(bool $forJoin = false): bool
    {
        if ($forJoin && $this->forcedEarlyBindingForJoin) {
            return false;
        } elseif ($this->relationship->isLateBound()) {
            return true;
        } else {
            //If we have to lazy load to avoid recursion, it will be late bound
            return !$this->parentCollection->isPropertyFetchable($this);
        }
    }

    public function isEager(): bool
    {
        if ($this->relationship->isEager()) {
            //Only if we can get it without recursion
            return $this->parentCollection->isPropertyFetchable($this);
        }

        return false;
    }

    public function getCollection(array $entities): iterable
    {
        $collection = $entities;
        $collectionClass = $this->relationship->collectionClass;
        if (!$collectionClass || $collectionClass == 'array') {
            if ($this->reflectionProperty->hasType()) {
                $collectionClass = $this->reflectionProperty->getType()->getName(); //Sometimes returns gibberish
                if ($collectionClass && (!class_exists($collectionClass) || !is_a($collectionClass, '\Traversable', true))) {
                    $collectionClass = $this->getTypeHacky($collectionClass);
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

    private function getTypeHacky(string $collectionClass)
    {
        //PHP ReflectionType seems buggy at times (on a Mac at least) - try a hacky way of checking the type
        try {
            $className = $this->className;
            $property = $this->propertyName;
            $hackyClass = new $className();
            $hackyClass->$property = 1; //Should cause an exception containing the actual type in the message
        } catch (\Throwable $ex) {
            $classStart = strpos($ex->getMessage(), 'must be an instance of ');
            if ($classStart !== false) {
                $classEnd = strpos($ex->getMessage(), ',') ?: strlen($ex->getMessage());
                $length = $classEnd - ($classStart + 23);
                $className = substr($ex->getMessage(), $classStart + 23, $length);
                if (class_exists($className) && is_a($className, '\Traversable', true)) {
                    return $className;
                }
            }
            $errorMessage = 'Could not determine collection class for %1$s. Please try adding a collectionClass attribute to the Relationship mapping for this property.';
            throw new MappingException(sprintf($errorMessage, $this->className . '::' . $this->propertyName));
        }
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
        $table = $this->getTableAlias() ?: $this->table->name;
        return $this->getJoinColumns($this->relationship->sourceJoinColumn, $table);
    }

    public function getTargetJoinColumns(): array
    {
        $table = $this->getTableAlias(true);
        return $this->getJoinColumns($this->relationship->targetJoinColumn, $table);
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
