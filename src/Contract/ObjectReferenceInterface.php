<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

//TODO: Come back and explain this better! It is not immediately obvious what these methods are for, and the
//explanations given are inadequate.

/**
 * Interface for a reference to an object which has not yet been saved, or has not been hydrated. So if you want to
 * save an object and associated one of its properties with a child object that already exists in the database, but
 * which you don't actually have a concrete instance of, instead of loading the child object from the database just so
 * that you can populate the property and save the relationship, you can create an object reference which just contains
 * the class name and primary key value. Objectiphy can then use this to save the relationship without ever having to
 * load the child object.
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
     * When creating a proxy object, we could be extending any old object, which might have its own constructor
     * arguments. In that case, we have to call this method separately.
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
