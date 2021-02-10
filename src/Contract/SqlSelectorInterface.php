<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\FindOptions;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * For an object that provides SQL for a select query.
 */
interface SqlSelectorInterface
{
    /**
     * Set runtime configuration for a find query.
     * @param FindOptions $options
     */
    public function setFindOptions(FindOptions $options): void;

    /**
     * Get the SQL necessary to select the records that will be used to hydrate the given entity.
     * @param SelectQueryInterface $query
     * @return string The query to execute.
     */
    public function getSelectSql(SelectQueryInterface $query): string;
}
