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
     * @var string Locally cached alias for this property's value in the data set.
     */
    private string $alias = '';

    /**
     * @param bool $includingPropertyName
     * @return string Fully qualified property path using dot notation by default.
     */
    public function getPropertyPath(bool $includingPropertyName = true, $separator = '.'): string
    {
        if (!$this->propertyPath) {
            $this->propertyPath = implode('.', $this->parentProperties);
        }

        $result = $separator == '.' ? $this->propertyPath : str_replace('.', $separator, $this->propertyPath);
        $result .= $includingPropertyName ? $separator . $this->propertyName : '';
        
        return $result;
    }

    public function getAlias(): string
    {
        //Try to use a nice alias with underscores. If there are clashes, get ugly.
        if (!isset($this->alias)) {
            $this->alias = $this->getPropertyPath(true, '_');
            if (array_key_exists($this->parentCollection->getColumns(), $this->alias)) {
                $this->alias = $this->getPropertyPath(true, '_-_');
            }
        }
        
        return $this->alias;
    }
}
