<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

/**
 * Mapping information to describe how the value of a property is stored and retrieved from a database column.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class Column
{
    /** @var string Name of column. */
    public string $name = '';
    
    /** @var string Data type (doctrine compatible). */
    public string $type = '';
    
    /**
     * @var string If type is string, and format is present, sprintf will be used. If type is datetimestring, the
     * DateTime->format() method will be called.
     */
    public string $format = '';
    
    /** @var bool Whether this column is part of the primary key. */
    public bool $isPrimaryKey = false;
    
    /**
     * @var bool | null Whether or not to prevent this field being persisted. If null, the default behaviour is
     * to evaluate to false for everything EXCEPT scalar joins (it is safer to assume scalar joins are read-only
     * as they are usually used to look up a value in a cross-reference table). You can override the default behaviour
     * by specifying a value of either true or false on the mapping definition.
     */
    public ?bool $isReadOnly = null;
    
    /**
     * @var string Name of aggregate function to use for this value (eg. 'AVG'). If specified, it will of course be
     * read-only.
     */
    public string $aggregateFunctionName = '';
    
    /**
     * @var string Name of property on the entity that holds the collection whose values should have the
     * aggregateFunctionName applied to them (only takes effect if a value is specifid in $aggregateFunctionName
     */
    public string $aggregateCollectionPropertyName = '';
    
    /**
     * @var string Name of property on the child class in the collection on which the aggregate function is being
     * performed, if applicable (eg. a COUNT does not require a property, but MAX does).
     */
    public string $aggregatePropertyName = '';
    
    /** @var string Name of property or properties to group by (comma separated) */
    public string $aggregateGroupBy = '';
}
