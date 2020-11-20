<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Mapping\Table;

interface SqlUpdaterInterface extends SqlProviderInterface
{
    /**
     * Set runtime configuration for a find query.
     */
    public function setSaveOptions(SaveOptions $saveOptions): void;

    /**
     * Get the SQL necessary to insert the given row.
     * @param Table $table Table being inserted into.
     * @param array $row The row to insert.
     * @return string[] An array of SQL queries to execute for inserting this record.
     */
    public function getInsertSql(Table $table, array $row): array;

    /**
     * Get the queries necessary to update the given row record.
     * @param Table $table Table whose rows are being updated.
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return string[] An array of queries to execute for updating the entity.
     */
    public function getUpdateSql(Table $table, array $row, array $pkValues): array;

    /**
     * Get the SQL queries necessary to replace the given row record.
     * @param Table $table Table whose rows are being replaced.
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return string[] An array of queries to execute for updating the entity.
     */
    public function getReplaceSql(Table $table, array $row, array $pkValues): array;
}
