<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Converts PascalCase or camelCase to snake_case. For foreign keys, the target join column name is used as a suffix if 
 * known, otherwise, '_id' is assumed. Extends ExactMatch so it can inherit the dePluralise method.
 */
class PascalCamelToSnake extends ExactMatch implements NamingStrategyInterface
{
    /**
     * Convert value of $name from property name to column name or class name to table name. This will only be used if 
     * no explicit mapping is present, and the configuration calls for unmapped names to be guessed.
     * @param string $name Value to convert.
     * @param int $type Type of thing the value represents (based on NamingStrategyInterface constants).
     * @param PropertyMapping | null $propertyMapping If $type is TYPE_SCALAR_PROPERTY or TYPE_RELATIONSHIP_PROPERTY,
     * the mapping details are provided here which can be used for making decisions about the conversion.
     * @return string The converted value.
     */
    public function convertName(
        string $name,
        int $type,
        ?PropertyMapping $propertyMapping = null
    ): string {
        $converted = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), '_');
        if ($type == self::TYPE_RELATIONSHIP_PROPERTY) {
            $targetColumnName = $propertyMapping->relationship->targetJoinColumn ?: 'id';
            $converted .= substr($converted, -3) != "_$targetColumnName" ? "_$targetColumnName" : '';
        }

        return $converted;
    }
}
