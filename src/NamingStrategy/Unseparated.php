<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Converts any case to all lower case with no separators (eg. camelCase to camelcase, snake_case to snakecase). For 
 * foreign keys, the target join column name is used as a suffix if known, otherwise, 'id' is assumed.
 */
class Unseparated implements NamingStrategyInterface
{
    public function convertName(
        string $name,
        int $type,
        ?PropertyMapping $propertyMapping = null
    ): string {
        $converted = strtolower($name);
        if ($type == self::TYPE_RELATIONSHIP_PROPERTY) {
            $targetColumnName = $propertyMapping->relationship->targetJoinColumn ?: 'id';
            $converted .= substr($converted, -2) != $targetColumnName ? $targetColumnName : '';
        }
        
        return $converted;
    }
}
