<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Orm\ConfigOptions;

/**
 * Mapping information to describe how a property relates to a property on another class, or a value from a table other
 * than the one associated with the class it belongs to.
 */
class Relationship
{
    const ONE_TO_ONE = 'one_to_one';
    const ONE_TO_MANY = 'one_to_many';
    const MANY_TO_ONE = 'many_to_one';
    const MANY_TO_MANY = 'many_to_many';

    /** @var bool Whether this relationship is part of the primary key. */
    public bool $isPrimaryKey;

    /** @var string One of the class constant values (eg. "one_to_one"). */
    public string $relationshipType;

    /** @var string Child entity class name. */
    public string $childClass;

    /**
     * @var string If the relationship is owned by the other class, specify the property on the other class that holds
     * the mapping.
     */
    public string $mappedBy;
    
    /**
     * @var boolean Whether or not to lazy load the data (for a child object if this property is the owning side)
     * - defaults to false for -to-one associations, and true for -to-many associations (ie. if this value is null).
     */
    public ?bool $lazyLoad;
    
    /**
     * @var string Name of target table (child entity) - defaults to the Objectiphy table annotation value on the
     * child class, if applicable. Also used to join to another table for scalar values that are not child objects.
     */
    public string $joinTable;
    
    /** @var string Name of column to join with on the target table (child entity). */
    public string $joinColumn;
    
    /** @var string Name of column to join with on the source table (parent entity). */
    public string $sourceJoinColumn;
    
    /** @var string "INNER" or "LEFT". */
    public string $joinType;
    
    /** @var string Custom SQL for join (eg. "vehicle.policy_id = policy.id"). */
    public string $joinSql;
    
    /** @var bool Whether this is actually an embedded (value) object that maps to several columns */
    public bool $isEmbedded;
    
    /** @var string Prefix to apply to embedded object column names */
    public string $embeddedColumnPrefix;
    
    /** @var array Properties to order children by */
    public array $orderBy;
    
    /**
     * @var string Class to use for collections of entities (must be traversable and take an array in constructor) -
     * applies to properties with a to-many relationship only.
     */
    public string $collectionType;
    
    /** @var bool Cascade deletes (if parent object is deleted, delete any children also) */
    public bool $cascadeDeletes;
    
    /** @var bool Orphan control (if child is removed from parent, delete the child, not just the relationship) */
    public bool $orphanRemoval;
    
    public function __construct($relationshipType)
    {
        $this->relationshipType = $relationshipType;
    }

    public static function getRelationshipTypes(): array
    {
        return [self::ONE_TO_ONE, self::ONE_TO_MANY, self::MANY_TO_ONE, self::MANY_TO_MANY];
    }

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
            'isPrimaryKey' => false,
            'relationshipType' => self::ONE_TO_ONE,
            'childClass' => '',
            'mappedBy' => '',
            'lazyLoad' => null,
            'joinTable' => '',
            'joinColumn' => '',
            'sourceJoinColumn' =>'',
            'joinType' => 'LEFT',
            'joinSql' => '',
            'isEmbedded' => false,
            'embeddedColumnPrefix' => '',
            'orderBy' => [],
            'collectionType' => 'array',
            'cascadeDeletes' => false,
            'orphanRemoval' => false,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($this->$key)) {
                $this->$key = $value;
            }
        }
    }

    public function isToOne(): bool
    {
        return in_array($this->relationshipType, [self::ONE_TO_ONE, self::MANY_TO_ONE]);
    }

    public function isToMany(): bool
    {
        return in_array($this->relationshipType, [self::ONE_TO_MANY, self::MANY_TO_MANY]);
    }

    public function isEager(ConfigOptions $config): bool
    {
        $eager = !$this->lazyLoad;
        if ($this->lazyLoad === null) {
            $eager = ($config->eagerLoadToOne && $this->isToOne()) || ($config->eagerLoadToMany && $this->isToMany());
        }

        return $eager;
    }
}
