<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

class JoinExpression
{
    public string $sourcePropertyReference;
    public string $sourceEntityClassName;
    public string $sourcePropertyName;
    public string $operator;
    public string $targetEntityClassName;
    public string $targetPropertyName;
    public string $joinAlias;
    public ?QueryBuilder $extraQueryBuilder = null;
    public array $extraCriteria = [];
    public string $type = 'LEFT';

    public function __construct(
        $sourcePropertyReference,
        $operator,
        $targetEntityClassName,
        $targetPropertyName,
        $joinAlias,
        QueryBuilder $extraQueryBuilder = null,
        $type = 'LEFT'
    ) {
        $this->sourcePropertyReference = $sourcePropertyReference;
        $this->operator = $operator;
        $this->targetEntityClassName = $targetEntityClassName;
        $this->targetPropertyName = $targetPropertyName;
        $this->joinAlias = $joinAlias;
        $this->extraQueryBuilder = $extraQueryBuilder;
        $this->type = $type;
    }
}
