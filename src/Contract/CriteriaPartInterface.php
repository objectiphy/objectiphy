<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Anything that is a valid part of a WHERE clause in an Objectiphy query should implement this.
 * There are no methods, it just enables us to ensure we have the right type of objects in the criteria array.
 */
interface CriteriaPartInterface extends QueryPartInterface
{

}
