<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for a class that implements a naming strategy when converting property names to column names or class 
 * names to table names (eg. to convert from camelCase or PascalCase to snake_case).
 */
interface NamingStrategyInterface
{
    public const TYPE_CLASS = 1;
    public const TYPE_SCALAR_PROPERTY = 2;
    public const TYPE_RELATIONSHIP_PROPERTY = 3;
    public const TYPE_STRING = 100;

    /**
     * Convert value of $name from property name to column name or class name to table name
     * @param string $name Value to convert.
     * @param int $type Type of thing the value represents (based on NamingStrategyInterface constants).
     * @param PropertyMapping|null $propertyMapping If $type is TYPE_SCALAR_PROPERTY or TYPE_RELATIONSHIP_PROPERTY,
     * the mapping details are provided here which can be used for making decisions about the conversion. 
     * @return string The converted value.
     */
    public function convertName(
        string $name,
        int $type,
        ?PropertyMapping $propertyMapping = null
    ): string;

    /**
     * Convert the plural form of a to-many relationship property into its singular equivalent (eg. policies to policy)
     * @param string $name
     * @return string
     */
    public function dePluralise(string $name): string;
}
