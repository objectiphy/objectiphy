<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Identifies an object as part of a query (eg. CriteriaExpression, FieldExpression)
 */
interface QueryPartInterface 
{
    public function __toString(): string;
}
