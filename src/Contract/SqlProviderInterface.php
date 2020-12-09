<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * For an object that provides SQL for a query.
 * @package Objectiphy\Objectiphy\Contract
 */
interface SqlProviderInterface
{
    /**
     * Return the parameter values to bind to the query.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams(): array;

    /**
     * Allow query parameters to be set (or cleared) manually.
     */
    public function setQueryParams(array $params = []): void;
}
