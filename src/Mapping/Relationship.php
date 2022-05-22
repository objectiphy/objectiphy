<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Annotations\AttributeTrait;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Mapping information to describe how a property relates to a property on another class, or a value from a table other
 * than the one associated with the class it belongs to.
 * The following annotation is just to stop the Doctrine annotation reader complaining if it comes across this.
 * @Annotation
 * @Target("PROPERTY")
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Relationship extends ObjectiphyAnnotation
{
    //use AttributeTrait;

    public const UNDEFINED = 'undefined';
    public const SCALAR = 'scalar';
    public const ONE_TO_ONE = 'one_to_one';
    public const ONE_TO_MANY = 'one_to_many';
    public const MANY_TO_ONE = 'many_to_one';
    public const MANY_TO_MANY = 'many_to_many';

    /**
     * @var bool Whether this relationship is part of the primary key. 
     */
    public bool $isPrimaryKey = false;

    /** 
     * @var string One of the class constant values (eg. "one_to_one"). 
     */
    public string $relationshipType = '';
    
    /** 
     * @var string Data type (applicable to scalar joins only) 
     */
    public string $type = '';

    /** 
     * @var string Child entity class name. 
     */
    public string $childClassName = '';

    /**
     * @var string If the relationship is owned by the other class, specify the property on the other class that holds
     * the mapping.
     */
    public string $mappedBy = '';
    
    /**
     * @var bool|null Whether or not to lazy load the data (for a child object if this property is the owning side)
     * - if null, defaults to false for -to-one associations, and true for -to-many associations.
     */
    public ?bool $lazyLoad = null;

    /**
     * @var string Name of target table (child entity) - defaults to the Objectiphy table annotation value on the
     * child class, if applicable. Also used to join to another table for scalar values that are not child objects
     * (scalar joins).
     */
    public string $joinTable = '';

    /** 
     * @var string Name of column to join with on the source table (parent entity). 
     */
    public string $sourceJoinColumn = '';

    /** 
     * @var string Name of column to join with on the target table (child entity). 
     */
    public string $targetJoinColumn = '';

    /**
     * @var string For many-to-many associations, the bridging table that links both entities.
     */
    public string $bridgeJoinTable = '';

    /**
     * @var string For many-to-many associations, the source column on the bridge table.
     */
    public string $bridgeSourceJoinColumn = '';

    /**
     * @var string For many-to-many associations, the target column on the bridge table.
     */
    public string $bridgeTargetJoinColumn = '';
    
    /** 
     * @var string Name of column that holds the value for a scalar join. 
     */
    public string $targetScalarValueColumn = '';
    
    /** 
     * @var string "INNER" or "LEFT". 
     */
    public string $joinType = 'LEFT';
    
    /** 
     * @var bool Whether this is actually an embedded (value) object that maps to several columns 
     */
    public bool $isEmbedded = false;
    
    /** 
     * @var string Prefix to apply to embedded object column names 
     */
    public string $embeddedColumnPrefix = '';
    
    /** 
     * @var array Properties to order children by (eg. ['modifiedDateTime' => 'DESC', 'id' => 'ASC']) 
     */
    public array $orderBy = [];

    /**
     * @var string Property (or column, or expression) to index children by (must be unique, or data will be lost).
     * If using a custom collection class, it is up to your collection what it does with array keys, but the array
     * passed to it will be associative if a value is set for this attribute.
     */
    public string $indexBy = '';

    /** 
     * @var bool Cascade deletes (if parent object is deleted, delete any children also) 
     */
    public bool $cascadeDeletes = false;
    
    /** 
     * @var bool Orphan control (if child is removed from parent, delete the child, not just the relationship) 
     */
    public bool $orphanRemoval = false;

    /** 
     * @var string Optionally specify a class to use to hold collections for toMany associations (defaults to a plain
     * old PHP array). If you need to use a custom factory to create your collection instances, pass your factory to 
     * RepositoryFactory before you create any repositories.
     */
    public string $collectionClass = '';

    /***
     * @deprecated
     */
    public string $joinSql = '';

    /**
     * @var string 
     */
    private string $targetPropertyName = '';
    
    /**
     * @var bool Global config setting (ie. the default value if not defined on this relationship)
     */
    private ?bool $eagerLoadToOne = null;

    /**
     * @var bool Global config setting (ie. the default value if not defined on this relationship)
     */
    private bool $eagerLoadToMany;

    /**
     * Relationship constructor.
     * @param array $values We have to use an array to stop Doctrine's annotation reader from complaining if it comes
     * across this (eg. when serializing using Symfony serialization groups). But we are only interested in the
     * relationshipType property at this point.
     * @throws ObjectiphyException
     */
    public function __construct(...$args)
    {
        foreach ($args ?? [] as $property => $value) {
            if (is_string($property) && property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        $relationshipType = $args['relationshipType'] ?? self::UNDEFINED;
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

    public function setConfigOptions(?bool $eagerLoadToOne, bool $eagerLoadToMany): void
    {
        $this->eagerLoadToOne = $eagerLoadToOne;
        $this->eagerLoadToMany = $eagerLoadToMany;
    }

    public function getEagerLoadToOne(): ?bool
    {
        return $this->eagerLoadToOne;
    }

    public function isDefined(): bool
    {
        return $this->relationshipType != self::UNDEFINED;
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
     * Convenience method for checking relationship type.
     * @return bool
     */
    public function isManyToMany(): bool
    {
        return $this->relationshipType == self::MANY_TO_MANY;
    }

    /**
     * Convenience method for checking relationship type.
     * @return bool
     */
    public function isFromMany(): bool
    {
        return in_array($this->relationshipType, [self::MANY_TO_ONE, self::MANY_TO_MANY]);
    }

    /**
     * Determines whether or not to eager load the child.
     * @param bool $isLateBound
     * @return bool
     */
    public function isEager(bool $isLateBound = false): bool
    {
        $eager = !$this->lazyLoad;
        if ($this->isDefined() && $this->lazyLoad === null) {
            if ($this->isToOne()) {
                $eager = ($this->eagerLoadToOne === null || $this->eagerLoadToOne) && $this->isToOne();
                if ($eager && $isLateBound && $this->eagerLoadToOne === null) {
                    $eager = false;
                }
            } else {
                $eager = $this->eagerLoadToMany && $this->isToMany();
            }
        }

        return $eager;
    }

    /**
     * Determines whether child is loaded in same query as parent, or in a separate query.
     * ToMany relationships and lazy loaded ones require a separate query.
     * See also PropertyMapping::isLateBound, which can artificially force late binding by
     * taking into account the context.
     * @param Table|null $childTable
     * @return bool
     */
    public function isLateBound(?Table $childTable = null): bool
    {
        if ($childTable && $childTable->repositoryClassName && $childTable->alwaysLateBind) {
            return true;
        } elseif ($this->isEmbedded) {
            return false;
        } else {
            return $this->isToMany() || !$this->isEager();
        }
    }

    public function isScalarJoin(): bool
    {
        return $this->targetScalarValueColumn ? true : false;
    }
    
    public function setTargetProperty(string $value): void
    {
        $this->targetPropertyName = $value;
    }
    
    public function getTargetProperty(): string
    {
        return $this->targetPropertyName;
    }

    /**
     * @param PropertyMapping $propertyMapping
     * @throws MappingException
     */
    public function validate(PropertyMapping $propertyMapping): void
    {
        $errorMessage = '';
        if ($this->isManyToMany() && !$this->bridgeJoinTable) {
            $errorMessage = 'Bridge join table has not been specified on many-to-many relationship for %1$s';
        } elseif ($this->isFromMany() && $this->cascadeDeletes) {
            $errorMessage = 'It is a bad idea to cascade deletes on a many-to-one or many-to-many relationship. Please remove the cascade option and use orphan removal instead.';
        } elseif ($this->isEmbedded || $this->isLateBound()) {
            return; //No join involved
        } elseif (!$this->joinTable) {
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
                $this->childClassName
            );
            throw new MappingException($errorMessage);
        }
    }
}
