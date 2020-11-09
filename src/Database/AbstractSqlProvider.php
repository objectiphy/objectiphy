<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Contract\SqlProviderInterface;

class AbstractSqlProvider implements SqlProviderInterface
{
    protected array $params = [];
    protected array $queryOverrides = [];

    /**
     * Return the parameter values to bind to the SQL statement. Where more than one SQL statement is involved, the
     * index identifies which one we are dealing with.
     * @param int|null $index Index of the SQL query.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams(int $index = null): array
    {
        $params = [];
        if ($index !== null && ($index != 0 || isset($this->params[$index]))) {
            $params = $this->params[$index] ?: [];
        } else {
            $params = !empty($this->params) ? $this->params : [];
        }

        return $params;
    }

    public function setQueryParams(array $params = []): void
    {
        $this->params = $params;
    }

    public function overrideQueryParts(array $queryOverrides): void
    {
        $this->queryOverrides = array_change_key_case($queryOverrides);
    }
}
