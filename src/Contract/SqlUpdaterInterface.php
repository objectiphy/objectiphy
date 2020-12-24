<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Mapping\Table;

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
     * @param InsertQueryInterface $query
     * @param bool $replace Whether to update existing record if it already exists.
     * @return string A query to execute for inserting the record.
     */
    public function getInsertSql(InsertQueryInterface $query, bool $replace = false): string;

    /**
     * Get the SQL necessary to perform the update.
     * @param UpdateQueryInterface $query
     * @param bool $replaceExisting
     * @return string A query to execute for updating the record(s).
     */
    public function getUpdateSql(UpdateQueryInterface $query, bool $replaceExisting = false): string;

    /**
     * Get the SQL queries necessary to replace the given row record.
     * @param Table $table Table whose rows are being replaced.
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return string[] An array of queries to execute for updating the entity.
     */
    public function getReplaceSql(Table $table, array $row, array $pkValues): array;
}
