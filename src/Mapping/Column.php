<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

/**
 * Mapping information to describe how the value of a property is stored and retrieved from a database column.
 */
class Column
{
    /** @var string Name of column. */
    public string $name;
    
    /** @var string Fully qualified column name, including table prefix. */
    public string $fullyQualifiedName;
    
    /** @var string Data type (doctrine compatible). */
    public string $type;
    
    /**
     * @var string If type is string, and format is present, sprintf will be used. If type is datetimestring, the
     * DateTime->format() method will be called.
     */
    public string $format;
    
    /** @var bool Whether this column is part of the primary key. */
    public bool $isPrimaryKey;
    
    /**
     * @var bool | null Whether or not to prevent this field being persisted. If null, the default behaviour is
     * to evaluate to false for everything EXCEPT scalar joins (it is safer to assume scalar joins are read-only
     * as they are usually used to look up a value in a cross-reference table). You can override the default behaviour
     * by specifying a value of either true or false on the mapping definition.
     */
    public ?bool $isReadOnly;
    
    /**
     * @var string Name of aggregate function to use for this value (eg. 'AVG'). If specified, it will of course be
     * read-only.
     */
    public string $aggregateFunction;
    
    /**
     * @var string Name of property on the entity that holds the collection whose values should have the
     * aggregateFunction applied to them (only takes effect if a value is specifid in $aggregateFunction
     */
    public string $aggregateCollectionPropertyName;
    
    /**
     * @var string Name of property on the child class in the collection on which the aggregate function is being
     * performed, if applicable (eg. a COUNT does not require a property, but MAX does).
     */
    public string $aggregatePropertyName;
    
    /** @var string Name of property or properties to group by (comma separated) */
    public string $aggregateGroupBy;

    /**
     * For any properties that are not set, populate a default value. This is done via a method rather than simply
     * giving each property a default value so that a decorating mapping provider can tell whether a value has been
     * assigned or not. If a default value was specified, it would be impossible to tell whether that was set by a
     * provider or is just a default value. Calling this method once is more performant and uses less code than using
     * getters and setters for every property.
     */
    public function populateDefaultValues()
    {
        $defaults = [
            'name' => '',
            'fullyQualifiedName' => '',
            'type' => 'string',
            'format' => '',
            'isPrimaryKey' => false,
            'isReadOnly' => null,
            'aggregateFunction' => '',
            'aggregateCollectionPropertyName' => '',
            'aggregatePropertyName' => '',
            'aggregateGroupBy' => '',
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($this->$key)) {
                $this->$key = $value;
            }
        }
    }
}
