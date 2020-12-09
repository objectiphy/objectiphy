<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * Identifies an object as part of a query (eg. CriteriaExpression, FieldExpression)
 * @package Objectiphy\Objectiphy\Contract
 */
interface QueryPartInterface 
{
    public function __toString(): string;
}
