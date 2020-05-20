<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @package Marmalade\Objectiphy
 * @author Russell Walker <russell.walker@marmalade.co.uk>
 * Interface for a reference to an object which has not yet been saved, or has not been hydrated.
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
     * @param $classNameOrObject
     * @param null $primaryKeyValue
     * @param string $primaryKeyPropertyName
     */
    public function setClassDetails($classNameOrObject, $primaryKeyValue = null, $primaryKeyPropertyName = 'id'): void;

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
