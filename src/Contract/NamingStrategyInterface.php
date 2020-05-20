<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * Interface for a class that implements a naming strategy when converting property names to column names (eg. to
 * convert from camelCase to snake_case).
 */
interface NamingStrategyInterface
{
    /**
     * Convert value of $name from property name to column name
     * @param string $name property name to convert.
     * @param bool $isChildClass Whether or not the property represents a child object.
     * @param null $parentClassName Class name of the object on which the property sits (parent of the child it
     * represents). Not used by default, but if you have a custom naming strategy that needs to use different 
     * rules for a particular class, it is helpful to know which one you are dealing with.
     * @return string The column name.
     */
    public function convertName($name, $isChildClass = false, $parentClassName = null): string;
}
