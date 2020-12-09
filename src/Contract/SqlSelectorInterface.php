<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\FindOptions;

/**
 * For an object that provides SQL for a select query.
 * @package Objectiphy\Objectiphy\Contract
 */
interface SqlSelectorInterface extends SqlProviderInterface
{
    /**
     * Set runtime configuration for a find query.
     */
    public function setFindOptions(FindOptions $findOptions): void;
        
    /**
     * Get the SQL necessary to select the records that will be used to hydrate the given entity.
     * @return string The query to execute.
     */
    public function getSelectSql(): string;
}
