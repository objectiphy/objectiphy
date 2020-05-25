<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

class PascalCamelToSnake implements NamingStrategyInterface
{
    public function convertName(
        string $name,
        int $type,
        ?PropertyMapping $propertyMapping
    ): string {
        $converted = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), '_');
        $converted = $isChildClass && substr($converted, -3) != '_id' ? $converted . '_id' : $converted;

        return $converted;
    }
}
