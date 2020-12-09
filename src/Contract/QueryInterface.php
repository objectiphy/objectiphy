<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * Identifies an object as a query (SelectQuery, UpdateQuery, InsertQuery, DeleteQuery)
 * @package Objectiphy\Objectiphy\Contract
 */
interface QueryInterface extends PropertyPathConsumerInterface
{
    public function __toString(): string;
}
