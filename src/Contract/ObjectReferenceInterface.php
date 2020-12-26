<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for an object that represents an instance of an entity to use either as a placeholder before the object 
 * has been persisted, so that the foreign key can be populated after persistence, or as a way of representing a child
 * object without having to actually hydrate the child object (where you just want to store a new association, but
 * are not updating the child entity).
 */
interface ObjectReferenceInterface
{
    /**
     * When creating an object reference, we will be extending any old entity, which might have its own constructor
     * arguments. In that case, we have to call this method separately.
     * @param string|object $classNameOrObject
     * @param array $pkValues Primary key values keyed on property name.
     */
    public function setClassDetails($classNameOrObject, array $pkValues = []): void;

    /**
     * @return array The primary key values keyed on property name.
     */
    public function getPkValues(): array;

    /**
     * Get the specified primary key value - prioritise trying to get it from the actual object, if possible, otherwise
     * use the local value.
     * @param string $propertyName
     * @return mixed The value of the primary key property.
     */
    public function getPkValue(string $propertyName);

    /**
     * Set the value of primary key property.
     * @param string $propertyName
     * @param mixed $value
     */
    public function setPrimaryKeyValue(string $propertyName, $value): void;

    /**
     * @return string The name of the class represented by this reference.
     */
    public function getClassName(): string;

    /**
     * @return object The object represented by this reference, if applicable.
     */
    public function getObject(): ?object;

    /**
     * @return string Generated hash to uniquely identify the object.
     */
    public function getObjectHash(): string;

    /**
     * @return string Either the primary key value as a string index, if known, or the object hash.
     */
    public function __toString(): string;
}
