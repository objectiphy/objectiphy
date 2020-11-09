<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

interface SqlProviderInterface
{
    /**
     * Return the parameter values to bind to the query. Where more than one query is involved, the index identifies
     * which one we are dealing with.
     * @param int|null $index Index of the query.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams(int $index = null): array;

    /**
     * Allow query parameters to be set (or cleared) manually.
     */
    public function setQueryParams(array $params = []): void;

    /**
     * Allow for parts of the SQL query to be overridden manually.
     * @param string[] $queryOverrides
     */
    public function overrideQueryParts(array $queryOverrides): void;
}
