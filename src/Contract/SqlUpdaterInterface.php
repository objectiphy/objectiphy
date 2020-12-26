<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\SaveOptions;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * For an object that provides SQL for a update query.
 */
interface SqlUpdaterInterface extends SqlProviderInterface
{
    /**
     * Set runtime configuration for a find query.
     * @param SaveOptions $options
     */
    public function setSaveOptions(SaveOptions $options): void;

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
}
