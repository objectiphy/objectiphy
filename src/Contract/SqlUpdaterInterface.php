<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\SaveOptions;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * For an object that provides SQL for a update query.
 */
interface SqlUpdaterInterface
{
    /**
     * Set runtime configuration for a find query.
     * @param SaveOptions $options
     */
    public function setSaveOptions(SaveOptions $options): void;

    /**
     * Get the SQL necessary to perform the insert.
     * @param InsertQueryInterface $query
     * @param bool $replaceExisting Whether to update existing record if it already exists.
     * @param bool $parseDelimiters Whether or not to look for delimiters in values (if false, all values are literal).
     * @return string A query to execute for inserting the record.
     */
    public function getInsertSql(InsertQueryInterface $query, bool $replaceExisting = false, bool $parseDelimiters = true): string;

    /**
     * Get the SQL necessary to perform the update.
     * @param UpdateQueryInterface $query
     * @param bool $replaceExisting Whether to update existing record if it already exists.
     * @param bool $parseDelimiters Whether or not to look for delimiters in values (if false, all values are literal).
     * @return string A query to execute for updating the record(s).
     */
    public function getUpdateSql(UpdateQueryInterface $query, bool $replaceExisting = false, bool $parseDelimiters = true): string;
}
