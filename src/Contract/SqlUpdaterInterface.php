<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

interface SqlUpdaterInterface extends SqlProviderInterface
{
    /**
     * Get the SQL necessary to insert the given row.
     * @param array $row The row to insert.
     * @return string[] An array of SQL queries to execute for inserting this record.
     */
    public function getInsertSql(array $row): array;

    /**
     * Get the queries necessary to update the given row record.
     * @param string $className Name of the parent entity class for the record being updated (used to get the
     * primary key column).
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return string[] An array of queries to execute for updating the entity.
     */
    public function getUpdateSql(string $className, array $row, array $pkValues): array;

    /**
     * Get the SQL queries necessary to replace the given row record.
     * @param string className Name of the parent entity class for the record being updated (used to get the
     * primary key column).
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return string[] An array of queries to execute for updating the entity.
     */
    public function getReplaceSql(string $className, array $row, array $pkValues): array;
}
