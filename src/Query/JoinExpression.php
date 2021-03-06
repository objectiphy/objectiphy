<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Represents a relationship in a query (without any criteria).
 */
class JoinExpression implements QueryPartInterface, JoinPartInterface, PropertyPathConsumerInterface
{
    public const JOIN_TYPE_LEFT = 'LEFT';
    public const JOIN_TYPE_INNER = 'INNER';

    public string $targetEntityClassName;
    public string $joinAlias;
    public string $type = self::JOIN_TYPE_LEFT;
    public ?PropertyMapping $propertyMapping = null; //For automatically joined relationships

    public function __construct(
        $targetEntityClassName,
        $joinAlias,
        $type = self::JOIN_TYPE_LEFT
    ) {
        $this->targetEntityClassName = $targetEntityClassName;
        $this->joinAlias = $joinAlias;
        $this->type = $type;
    }
    
    public function __toString(): string
    {
        $joinString = $this->type . ' JOIN ';
        $joinString .= $this->targetEntityClassName;
        $joinString .= ' ' . $this->joinAlias;

        return $joinString;
    }

    public function getPropertyPaths(): array
    {
        $paths = [
            $this->sourceProperty ?? null,
            $this->targetProperty ?? null,
        ];

        return array_filter($paths);
    }
}
