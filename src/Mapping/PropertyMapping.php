<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

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

    /**
     * @var string Well, duh.
     */
    public string $propertyName;

    /**
     * @var array Indexed array of parent property names.
     */
    public array $parentProperties = [];

    /**
     * @var MappingCollection Collection to which this mapping belongs
     */
    public MappingCollection $parentCollection;

    /**
     * @var Relationship If this property represents a relationship to a child entity, the relationship annotation.
     */
    public Relationship $relationship;

    /**
     * @var Table If the value of this property is stored in a column on the entity's table, the table
     * annotation.
     */
    public Table $table;

    /**
     * @var Column If the value of this property is stored in a column on the entity's table, the column
     * annotation.
     */
    public Column $column;

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

    public function __construct(
        string $className,
        string $propertyName,
        Table $table,
        Column $column,
        Relationship $relationship,
        array $parentProperties = []
    ) {
        $this->className = $className;
        $this->propertyName = $propertyName;
        $this->table = $table;
        $this->column = $column;
        $this->relationship = $relationship;
        $this->parentProperties = $parentProperties;
    }

    /**
     * Get the fully qualified property path using dot notation by default.
     * @param bool $includingPropertyName
     * @return string
     */
    public function getPropertyPath(bool $includingPropertyName = true, $separator = '.'): string
    {
        if (!$this->propertyPath) {
            $this->propertyPath = implode('.', $this->parentProperties);
        }

        $result = $separator == '.' ? $this->propertyPath : str_replace('.', $separator, $this->propertyPath);
        $result .= $includingPropertyName ? $separator . $this->propertyName : '';

        return ltrim($result, $separator);
    }
    
    public function isScalarValue(): bool
    {
        return !$this->relationship->isDefined() || $this->relationship->isScalarJoin();
    }
    
    public function getChildClassName(bool $eagerLoadedOnly = false): string
    {
        if ($eagerLoadedOnly && !$this->relationship->isEager()) {
            return '';
        } else {
            return $this->relationship->childClassName;
        }
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
            $this->alias = $this->getPropertyPath(true, '_');
            if (array_key_exists($this->alias, $this->parentCollection->getColumnDefinitions())) {
                $this->alias = $this->getPropertyPath(true, '_-_');
            }
        }

        return $this->alias;
    }

    public function getTableAlias(bool $forJoin = false): string
    {
        if (empty($this->tableAlias)
            && count($this->parentProperties) > 0 //No need to alias scalar properties of main entity
            && strpos($this->column->name, '.') === false) { //Already mapped to an alias manually, so don't mess 
            $this->tableAlias = rtrim('obj_alias_' . implode('_', $this->parentProperties), '_');
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

    public function getSourceJoinColumns(): array
    {
        $table = $this->getTableAlias();
        $table = $table ?: $this->table->name;
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
