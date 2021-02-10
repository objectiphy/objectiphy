<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * For an object that provides SQL for a delete query.
 */
interface SqlDeleterInterface
{
    /**
     * @param DeleteQueryInterface $query
     * @return string SQL to execute to perform the delete.
     */
    public function getDeleteSql(DeleteQueryInterface $query): string;
}
