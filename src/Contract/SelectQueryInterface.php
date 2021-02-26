<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Like an SQL query, but with expressions relating to objects and properties.
 */
interface SelectQueryInterface extends QueryInterface
{
    public function setSelect(FieldExpression ...$fields): void;

    public function getSelect(): array;

    public function setFrom(string $className): void;

    public function getFrom(): string;

    public function setGroupBy(FieldExpression ...$fields): void;

    public function getGroupBy(): array;

    public function setHaving(CriteriaExpression ...$criteria): void;

    public function getHaving(): array;

    public function setOrderBy(FieldExpression ...$fields): void;

    public function getOrderBy(): array;

    public function setLimit(int $limit): void;

    public function getLimit(): ?int;

    public function setOffset(int $offset): void;

    public function getOffset(): ?int;
}
