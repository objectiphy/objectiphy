<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;

class ExactMatch implements NamingStrategyInterface
{
    public function convertName(
        string $name,
        ?\ReflectionClass $reflectionClass = null,
        ?\ReflectionProperty $reflectionProperty = null
    ): string {
        return $name;
    }
}
