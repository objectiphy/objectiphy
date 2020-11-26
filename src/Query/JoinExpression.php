<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

class JoinExpression implements QueryPartInterface, PropertyPathConsumerInterface
{
    public const JOIN_TYPE_LEFT = 'LEFT';
    public const JOIN_TYPE_INNER = 'INNER';

    public string $sourceProperty;
    public string $sourceEntityClassName;
    public string $operator;
    public string $targetEntityClassName;
    public string $targetProperty;
    public string $joinAlias;
    public ?QueryBuilder $extraQueryBuilder = null;

    /**
     * @var CriteriaPartInterface[]
     */
    public array $extraCriteria = [];

    public string $type = self::JOIN_TYPE_LEFT;
    public ?PropertyMapping $propertyMapping = null; //For automatically joined relationships

    public function __construct(
        $sourceProperty,
        $operator,
        $targetEntityClassName,
        $targetPropertyName,
        $joinAlias,
        QueryBuilder $extraQueryBuilder = null,
        $type = self::JOIN_TYPE_LEFT
    ) {
        $this->sourceProperty = $sourceProperty;
        $this->operator = $operator;
        $this->targetEntityClassName = $targetEntityClassName;
        $this->targetPropertyName = $targetPropertyName;
        $this->joinAlias = $joinAlias;
        $this->extraQueryBuilder = $extraQueryBuilder;
        $this->type = $type;
    }
    
    public function __toString(): string
    {
        $joinString = $this->type . ' JOIN ';
        $joinString .= $this->targetEntityClassName;
        $joinString .= ' ' . $this->joinAlias;
        $joinString .= ' ON ';
        $joinString .= '`' . $this->sourceProperty . '`';
        $joinString .= ' ' . $this->operator . ' ';
        $joinString .= '`' . $this->sourceProperty . '.' . $this->targetPropertyName . '`';
        $joinString .= implode(' AND ', $this->extraCriteria);

        return $joinString;
    }

    public function getPropertyPaths(): array
    {
        $paths = [
            $this->sourceProperty ?? null,
            $this->targetProperty ?? null
        ];

        foreach ($this->extraCriteria as $criteriaExpression) {
            $paths = array_merge($paths, $criteriaExpression->getPropertyPaths());
        }

        return array_filter($paths);
    }
}
