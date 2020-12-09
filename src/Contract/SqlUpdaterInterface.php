<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Query\InsertQuery;
use Objectiphy\Objectiphy\Query\UpdateQuery;

/**
 * For an object that provides SQL for a update query.
 * @package Objectiphy\Objectiphy\Contract
 */
interface SqlUpdaterInterface extends SqlProviderInterface
{
    /**
     * Set runtime configuration for a find query.
     */
    public function setSaveOptions(SaveOptions $saveOptions): void;

    /**
     * Get the SQL necessary to perform the insert.
     * @param InsertQuery $query
     * @return string A query to execute for inserting the record.
     */
    public function getInsertSql(InsertQuery $query): string;

    /**
     * Get the SQL necessary to perform the update.
     * @param UpdateQuery $query
     * @param bool $replaceExisting
     * @return string A query to execute for updating the record(s).
     */
    public function getUpdateSql(UpdateQuery $query, bool $replaceExisting = false): string;

    /**
     * Get the SQL queries necessary to replace the given row record.
     * @param Table $table Table whose rows are being replaced.
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return string[] An array of queries to execute for updating the entity.
     */
    public function getReplaceSql(Table $table, array $row, array $pkValues): array;
}
