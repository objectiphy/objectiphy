<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Query\FieldExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Identifies an object as a query (SelectQuery, UpdateQuery, InsertQuery, DeleteQuery)
 */
interface QueryInterface extends PropertyPathConsumerInterface
{
    public function __toString(): string;
    
    public function setFields(FieldExpression ...$fields): void;

    public function getFields(): array;

    public function setClassName(string $className): void;

    public function getClassName(): string;

    public function setJoins(JoinPartInterface ...$joins): void;

    public function getJoins(): array;

    public function setWhere(CriteriaPartInterface ...$criteria): void;

    public function getWhere(): array;
}
