<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * A 'dummy' naming strategy that does not perform any conversion.
 */
class ExactMatch implements NamingStrategyInterface
{
    public function convertName(
        string $name,
        int $type,
        ?PropertyMapping $propertyMapping = null
    ): string {
        return $name;
    }
}
