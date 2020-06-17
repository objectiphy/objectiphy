<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

/**
 * Mapping information for a particular property.
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
            if (array_key_exists($this->alias, $this->parentCollection->getColumns())) {
                $this->alias = $this->getPropertyPath(true, '_-_');
            }
        }
        
        return $this->alias;
    }
}
