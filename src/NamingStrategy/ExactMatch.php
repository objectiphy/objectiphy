<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;

/**
 * A 'dummy' naming strategy that does not perform any conversion.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
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
