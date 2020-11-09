<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

interface SqlDeleterInterface extends SqlProviderInterface
{
    /**
     * @param string $entityClassName Class name of entity being removed
     * @param mixed $keyValue Value of primary key for record to delete.
     * @return string[] An array of queries to execute for removing the entity.
     */
    public function getDeleteSql($entityClassName, $keyValue): array;
}
