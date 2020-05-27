<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;

class Unseparated implements NamingStrategyInterface
{
    public function convertName(
        string $name,
        ?\ReflectionClass $reflectionClass = null,
        ?\ReflectionProperty $reflectionProperty = null
    ): string {
        $converted = strtolower($name);
        if ($type == self::TYPE_RELATIONSHIP_PROPERTY) {
            $targetColumnName = $propertyMapping->relationship->targetJoinColumn ?? 'id';
            $converted .= substr($converted, -2) != $targetColumnName ? $targetColumnName : '';
        }
        
        return $converted;
    }
}
