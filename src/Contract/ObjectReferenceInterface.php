<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * Interface for an object that represents an instance of an entity to use either as a placeholder before the object 
 * has been persisted, so that the foreign key can be populated after persistence, or as a way of representing a child
 * object without having to actually hydrate the child object (where you just want to store a new association, but
 * are not updating the child entity).
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface ObjectReferenceInterface
{
    /**
     * Get the primary key value - prioritise trying to get it from the actual object, if possible, otherwise use the
     * static value.
     * @return mixed The value of the primary key.
     */
    public function getPrimaryKeyValue();

    /**
     * Set either the class name and primary key value, or an instance of the entity that does not yet have a key value.
     * @param string | object $classNameOrObject
     * @param mixed | null $primaryKeyValue
     * @param string $primaryKeyPropertyName
     */
    public function setClassDetails(
        $classNameOrObject,
        $primaryKeyValue = null,
        string $primaryKeyPropertyName = 'id'
    ): void;

    /**
     * @return string The name of the class represented by this reference.
     */
    public function getClassName(): string;

    /**
     * @return object The object represented by this reference, if applicable.
     */
    public function getObject(): object;

    /**
     * @return string Generated hash to uniquely identify the object.
     */
    public function getObjectHash(): string;

    /**
     * @return string Either the primary key value, if known, or the object hash.
     */
    public function __toString();
}
