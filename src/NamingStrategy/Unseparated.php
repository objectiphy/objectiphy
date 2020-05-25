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
        $converted = $isChildClass && substr($converted, -2) != 'id' ? $converted . 'id' : $converted;

        return $converted;
    }
}
