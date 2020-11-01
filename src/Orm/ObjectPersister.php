<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectPersister
{
    /**
     * Just passing through.
     * @param array $queryOverrides
     */
    public function overrideQueryParts(array $queryOverrides)
    {
        $this->sqlBuilder->overrideQueryParts($queryOverrides);
    }
}
