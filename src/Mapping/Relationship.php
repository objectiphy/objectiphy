<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
 * Mapping information to describe how a property relates to a property on another class, or a value from a table other
 * than the one associated with the class it belongs to.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class Relationship
{
    const UNDEFINED = 'undefined';
    const SCALAR = 'scalar';
    const ONE_TO_ONE = 'one_to_one';
    const ONE_TO_MANY = 'one_to_many';
    const MANY_TO_ONE = 'many_to_one';
    const MANY_TO_MANY = 'many_to_many';

    /** @var bool Whether this relationship is part of the primary key. */
    public bool $isPrimaryKey = false;

    /** @var string One of the class constant values (eg. "one_to_one"). */
    public string $relationshipType = '';
    
    /** @var string Data type (applicable to scalar joins only) */
    public string $type = '';

    /** @var string Child entity class name. */
    public string $childClassName = '';

    /**
     * @var string If the relationship is owned by the other class, specify the property on the other class that holds
     * the mapping.
     */
    public string $mappedBy = '';
    
    /**
     * @var boolean | null Whether or not to lazy load the data (for a child object if this property is the owning side)
     * - if null, defaults to false for -to-one associations, and true for -to-many associations.
     */
    public ?bool $lazyLoad = null;

    /**
     * @var string Name of target table (child entity) - defaults to the Objectiphy table annotation value on the
     * child class, if applicable. Also used to join to another table for scalar values that are not child objects
     * (scalar joins).
     */
    public string $joinTable = '';

    /** @var string Name of column to join with on the source table (parent entity). */
    public string $sourceJoinColumn = '';

    /** @var string Name of column to join with on the target table (child entity). */
    public string $targetJoinColumn = '';
    
    /** @var string Name of column that holds the value for a scalar join. */
    public string $targetScalarValueColumn = '';
    
    /** @var string "INNER" or "LEFT". */
    public string $joinType = 'LEFT';
    
    /**
     * @var string Custom SQL for join (eg. "vehicle.policy_id = policy.id"). Not usually required, as it will be
     * generated automatically, but if you have special requirements you can specify something to use instead.
     */
    public string $joinSql = '';
    
    /** @var bool Whether this is actually an embedded (value) object that maps to several columns */
    public bool $isEmbedded = false;
    
    /** @var string Prefix to apply to embedded object column names */
    public string $embeddedColumnPrefix = '';
    
    /** @var array Properties to order children by (eg. ['modifiedDateTime' => 'DESC', 'id' => 'ASC']) */
    public array $orderBy = [];

    /** @var bool Cascade deletes (if parent object is deleted, delete any children also) */
    public bool $cascadeDeletes = false;
    
    /** @var bool Orphan control (if child is removed from parent, delete the child, not just the relationship) */
    public bool $orphanRemoval = false;

    /**
     * @var string Name of a factory class that can be used to create custom collection classes for collections of
     * entities (for properties with a to-many relationship only). If supplied, this must be the fully qualified class
     * name of a class that implements CollectionFactoryInterface. Defaults to a plain old PHP array for collections
     * if no factory class supplied.
     */
    private string $collectionFactoryClass = '';

    public function __construct(string $relationshipType)
    {
        if (!in_array($relationshipType, self::getRelationshipTypes())) {
            $errorMessage = sprintf(
                'Invalid relationship type: %1$s. Valid types are: %2$s',
                $relationshipType,
                implode(', ', self::getRelationshipTypes())
            );
            throw new ObjectiphyException($errorMessage);
        }
        
        $this->relationshipType = $relationshipType;
    }

    /**
     * Static method to get an array of all of the relationship types.
     * @return string[]
     */
    public static function getRelationshipTypes(): array
    {
        return [self::ONE_TO_ONE, self::ONE_TO_MANY, self::MANY_TO_ONE, self::MANY_TO_MANY, self::SCALAR, self::UNDEFINED];
    }

    public function isDefined(): bool 
    {
        return $this->relationshipType != self::UNDEFINED;
    }
    
    /**
     * Setter for custom collection class factory (ensures the value supplied implements the correct interface).
     * @param string $factoryClassName
     * @throws ObjectiphyException
     */
    public function setCollectionFactoryClass(string $factoryClassName)
    {
        if ($factoryClassName 
            && $factoryClassName != 'array' 
            && !is_a($factoryClassName, CollectionFactoryInterface::class, true)
        ) {
            $message = 'Value of collectionFactoryClass (%1$s) is not valid - it must be the fully qualified class name of a class that implements %2$s.';
            throw new ObjectiphyException(sprintf($message, $factoryClassName, CollectionFactoryInterface::class));
        }
        
        $this->collectionFactoryClass = $factoryClassName;
    }

    /**
     * Getter for custom collection class factory.
     * @return string
     */
    public function getCollectionFactoryClass()
    {
        return $this->collectionFactoryClass;
    }

    /**
     * Convenience method for checking relationship type.
     * @return bool
     */
    public function isToOne(): bool
    {
        return in_array($this->relationshipType, [self::ONE_TO_ONE, self::MANY_TO_ONE]);
    }

    /**
     * Convenience method for checking relationship type.
     * @return bool
     */
    public function isToMany(): bool
    {
        return in_array($this->relationshipType, [self::ONE_TO_MANY, self::MANY_TO_MANY]);
    }

    /**
     * Determines whether or not to eager load the child.
     * @return bool
     */
    public function isEager(bool $eagerLoadToOne, bool $eagerLoadToMany): bool
    {
        $eager = !$this->lazyLoad;
        if ($this->lazyLoad === null) {
            $eager = ($eagerLoadToOne && $this->isToOne()) || ($eagerLoadToMany && $this->isToMany());
        }

        return $eager;
    }

    public function isScalarJoin(): bool
    {
        return $this->targetScalarValueColumn ? true : false;
    }

    private function validateRelationship(PropertyMapping $propertyMapping)
    {
        $errorMessage = '';
        if (!$this->joinTable) {
            $errorMessage = 'Could not determine join table for relationship from %1$s to %2$s';
        } elseif (!$this->sourceJoinColumn) {
            $errorMessage = 'Could not determine source join column for relationship from %1$s to %2$s';
        } elseif (!$this->targetJoinColumn) {
            $errorMessage = 'Could not determine target join column for relationship from %1$s to %2$s';
        } else {
            $sourceColumnCount = count(explode(',', $this->sourceJoinColumn));
            $targetColumnCount = count(explode(',', $this->targetJoinColumn));
            if ($sourceColumnCount != $targetColumnCount) {
                $errorMessage = 'On the relationship between %1$s and %2$s, the join consists of more than one column (this can happen automatically if there is a composite primary key). There must be an equal number of columns on both sides of the join. You can specify multiple columns in your mapping by separating them with a comma.';
            }
        }

        if ($errorMessage) {
            $errorMessage = sprintf(
                $errorMessage,
                $propertyMapping->className . '::' . $propertyMapping->propertyName,
                $relationship->type
            );
            throw new MappingException($errorMessage);
        }
    }
}
