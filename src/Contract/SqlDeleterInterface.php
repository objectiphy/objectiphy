<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * For an object that provides SQL for a delete query.
 * @package Objectiphy\Objectiphy\Contract
 */
interface SqlDeleterInterface extends SqlProviderInterface
{
    /**
     * @param string $entityClassName Class name of entity being removed
     * @param mixed $keyValue Value of primary key for record to delete.
     * @return string A query to execute for removing the entity.
     */
    public function getDeleteSql(DeleteQueryInterface $query): string;
}
