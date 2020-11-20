<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlProviderInterface;
use Objectiphy\Objectiphy\Query\Query;

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

    /**
     * Replace prepared statement parameters with actual values (for debugging output only, not for execution!)
     * @param string $query Parameterised SQL string
     * @param array $params Parameter values to replace tokens with
     * @return string SQL string with values instead of parameters
     */
    public function replaceTokens($query, $params)
    {
        if (count($params)) {
            foreach (array_reverse($params) as $key => $value) { //Don't want to replace param_10 with column name for param_1 followed by a zero!
                $query = str_replace(
                    ':' . $key,
                    (in_array($value, [null, true, false], true) ? var_export($value, true) : "'$value'"),
                    $query
                );
            }
        }

        return $query;
    }

    /**
     * Convert "database.table.column" to "`database`.`table`.`column`". As the input does not
     * come from a user, but from mapping definitions, we will not sanitize in case there is a
     * reason a for a developer wanting to break out of the backticks to do something filthy.
     * @param string $tableColumnString Database/Table/Column separated by a dot.
     * @param string $delimiter Character to wrap tables and columns in.
     * @return string Backtick separated string equivalent.
     */
    protected function delimit(string $tableColumnString, string $delimiter = '`')
    {
        $delimited = '';
        if ($tableColumnString) {
            $delimited = $delimiter . implode("$delimiter.$delimiter", explode('.', $tableColumnString)) . $delimiter;
        }

        return $delimited;
    }

    protected function overrideQueryPart($part, $generatedSql, $params, QueryInterface $query)
    {
        $override = !empty($this->queryOverrides[strtolower($part)])
            ? $this->queryOverrides[strtolower($part)]
            : $generatedSql;
        if (is_callable($override)) {
            $override = call_user_func_array($override, [$generatedSql, $query, $params]);
        } elseif (!is_string($override)) { //We don't know what the heck this is - just use our generated SQL
            $override = $generatedSql;
        }

        return $override;
    }
}
