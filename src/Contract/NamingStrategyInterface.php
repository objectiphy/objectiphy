<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * Interface for a class that implements a naming strategy when converting property names to column names or class 
 * names to table names (eg. to convert from camelCase or PascalCase to snake_case).
 */
interface NamingStrategyInterface
{
    /**
     * Convert value of $name from property name to column name or class name to table name
     * @param string $name Property or class name to convert.
     * @param \ReflectionClass | null $reflectionClass If $name belongs to a class, this is the class.
     * @param \ReflectionProperty | null $reflectionProperty If $name belongs to a property, this is the property.
     * @return string The converted name.
     */
    public function convertName(
        string $name, 
        ?\ReflectionClass $reflectionClass = null, 
        ?\ReflectionProperty $reflectionProperty = null
    ): string;
}
