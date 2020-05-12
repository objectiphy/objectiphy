<?php

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Annotation\Relationship;
use Objectiphy\Objectiphy\Annotation\Column;

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
     * @var string Alias used to refer to the value in the data set, if applicable.
     */
    public string $alias = '';

    /**
     * @var array Indexed array of parent property names.
     */
    public array $parentProperties = [];
    
    /** 
     * @var MappingCollection|null Collection to which this mapping belongs 
     */
    public ?MappingCollection $parentCollection = null;
    
    /** 
     * @var Relationship|null If this property represents a relationship to a child entity, the relationship annotation.
     */
    public ?Relationship $relationship = null;

    /**
     * @var Column|null If the value of this property is stored in a column on the entity's table, the column 
     * annotation.
     */
    public ?Column $column = null;

    /**
     * @var string Locally cached fully qualified property path using dot notation.
     */
    private string $propertyPath = '';

    /**
     * @param bool $includingPropertyName
     * @return string Fully qualified property path using dot notation.
     */
    public function getPropertyPath(bool $includingPropertyName = true): string
    {
        if (!$this->propertyPath) {
            $pathParts = array_merge($this->parentProperties, ($includingPropertyName ? [$this->propertyName] : []));
            $this->propertyPath = implode('.', $pathParts);
        }
        
        return $this->propertyPath;
    }

    /**
     * @param bool $useShortName
     * @return string Alias or unqualified column name, (ie. as it appears in the returned data set)
     */
    public function getShortColumn(bool $useAlias = true)
    {
        return $useAlias && $this->alias ? $this->alias : $this->columnShortName; 
    }
}
