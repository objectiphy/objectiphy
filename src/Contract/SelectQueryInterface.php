<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;

interface SelectQueryInterface extends QueryInterface
{
    public function setSelect(FieldExpression ...$fields): void;

    public function getSelect(): array;

    public function setFrom(string $className): void;

    public function getFrom(): string;

    public function setGroupBy(FieldExpression ...$fields);

    public function getGroupBy(): array;

    public function setHaving(CriteriaExpression ...$critiera);

    public function getHaving(): array;

    public function setOrderBy(FieldExpression ...$fields);

    public function getOrderBy(): array;

    public function setLimit(int $limit);

    public function getLimit(): ?int;

    public function setOffset(int $offset);

    public function getOffset(): ?int;

    public function finalise(MappingCollection $mappingCollection, ?string $className = null);
}
