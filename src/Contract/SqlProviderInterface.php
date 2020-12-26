<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * For an object that provides SQL for a query.
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
     * @param array $params
     */
    public function setQueryParams(array $params = []): void;
}
