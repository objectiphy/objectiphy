<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Annotations\AttributeTrait;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Mapping information to describe how the value of a property is stored and retrieved from a database column.
 * The following annotation is just to stop the Doctrine annotation reader complaining if it comes across this.
 * @Annotation
 * @Target("PROPERTY")
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column extends ObjectiphyAnnotation
{
    use AttributeTrait;

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
    
    /** @var bool For primary keys, whether or not they auto-increment. Set to false to supply your own PK value. */
    public bool $autoIncrement = true;
    
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

    /** 
     * @var string Name of property or properties to group by (comma separated) 
     */
    public string $aggregateGroupBy = '';

    /**
     * @var array Key value pairs - for cases where you have a limited number of possible values in the database,
     * and you want to map those to different values when returning the results (for example to use a human readable
     * description instead of a code) - effectively a mini lookup table defined on the property. This can be a simple 
     * key/value pair associative array, or a key: operator/value array, with optional default condition. For example:
     * [
     *     "50" => "Exactly 50"
     *     "100" => [
     *         "operator" => ">"
     *         "value" => "Greater than 100"
     *     ]
     *     "ELSE" => "Something else"
     * ]
     * In this example, if the value in the field is 125, the value returned in the results would be "Greater than 100".
     * For any values that are directly mapped (ie. operator is unspecified or '='), the translation of values is 
     * reversed when you save the entity. However, PLEASE NOTE: if you use any operator other than =, or use the 'ELSE' 
     * clause, it will not be possible to update the field to hold that value - the property will be treated as 
     * read-only (because it will be impossible to tell what the actual database value should be).
     */
    public array $dataMap = [];
}
