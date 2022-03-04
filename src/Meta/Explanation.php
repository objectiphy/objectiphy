<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Meta;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\QueryInterceptorInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * THIS IS FOR DEBUGGING/PROFILING PURPOSES ONLY! Do not ever use the output of this class to execute database queries 
 * via code or in production, as it deliberately does not sanitise user input. It is ONLY to be used to help you 
 * understand what is going on under the hood.
 */
class Explanation implements ExplanationInterface
{
    private SqlStringReplacer $stringReplacer;

    /**
     * @var QueryInterface[]
     */
    private array $queryHistory = [];

    /**
     * @var array string[]
     */
    private array $sqlHistory = [];

    private array $paramHistory = [];

    /**
     * @var MappingCollection[]
     */
    private array $mapping = [];

    /**
     * @var ConfigOptions[]
     */
    private array $config = [];

    private ?QueryInterceptorInterface $queryInterceptor = null;

    public function __construct(SqlStringReplacer $stringReplacer)
    {
        $this->stringReplacer = $stringReplacer;
    }

    public function setInterceptor(?QueryInterceptorInterface $queryInterceptor = null)
    {
        $this->queryInterceptor = $queryInterceptor;
    }

    /**
     * Used internally to record query activity
     * @param QueryInterface $query
     * @param string $sql
     * @param MappingCollection|null $mappingCollection
     * @param ConfigOptions|null $config
     */
    public function addQuery(
        QueryInterface $query,
        string $sql,
        MappingCollection $mappingCollection = null,
        ConfigOptions $config = null
    ): void {
        $params = $query->getParams();
        if (isset($this->queryInterceptor)) {
            $this->queryInterceptor->intercept($sql, $params);
        }
        if ($config->recordQueries || (is_null($config->recordQueries) && $config->devMode)) {
            $this->queryHistory[] = $query;
            $this->sqlHistory[] = $sql;
            $this->paramHistory[] = $params;
            $this->mapping[] = $mappingCollection;
            $this->config[] = $config;
        }
    }

    /**
     * Delete all collected data (to free up memory for large and/or multiple queries).
     */
    public function clear(): void
    {
        $this->queryHistory = [];
        $this->sqlHistory = [];
        $this->paramHistory = [];
        $this->mapping = [];
        $this->config = [];
    }

    /**
     * Get the last query that was executed.
     * @return QueryInterface|null
     */
    public function getQuery(): ?QueryInterface
    {
        return $this->queryHistory ? $this->queryHistory[array_key_last($this->queryHistory)] : null;
    }

    /**
     * Get all the queries that were executed.
     * @return QueryInterface[] All of the queries that have been executed.
     */
    public function getQueryHistory(): array
    {
        return $this->queryHistory;
    }

    /**
     * Get the last SQL query that was executed.
     * @param bool $parameterise Whether or not to replace parameter tokens with their values
     * @return string The SQL, with parameters converted to values so that the string can be pasted
     * into a database GUI and executed without having to replace values manually (when Objectiphy executes the query,
     * it does NOT use this exact string, it uses prepared statements).
     */
    public function getSql(bool $parameterise = true): string
    {
        if ($this->sqlHistory) {
            $lastQuery = $this->sqlHistory[array_key_last($this->sqlHistory)];
            $lastParams = $this->getParams();

            return $parameterise ? $this->stringReplacer->replaceTokens($lastQuery, $lastParams) : $lastQuery;
        }

        return '';
    }

    /**
     * Get all the SQL queries that were executed.
     * @param bool $parameterise Whether or not to replace parameter tokens with their values
     * @return array All of the queries that have been executed, with parameters converted to values so that the string
     * can be pasted into a database GUI and executed without having to replace values manually (when Objectiphy
     * executes the query, it does NOT use this exact string, it uses prepared statements).
     */
    public function getSqlHistory(bool $parameterise = true): array
    {
        $sqlHistory = $this->sqlHistory;
        if ($parameterise) {
            array_walk($sqlHistory, function (&$sql, $index, $paramHistory = []) {
                $sql = $this->stringReplacer->replaceTokens($sql, $paramHistory[$index]);
            }, $this->paramHistory);
        }

        return $sqlHistory ?: [];
    }

    /**
     * @return array The parameters used in the last query as an associative array.
     */
    public function getParams(): array
    {
        return $this->paramHistory ? $this->paramHistory[array_key_last($this->paramHistory)] : [];
    }

    /**
     * @return array An indexed array of all the parameters used in all queries. Each element is an associative array.
     * The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getParamHistory(): array
    {
        return $this->paramHistory;
    }

    /**
     * @return MappingCollection|null The mapping collection used for the last query.
     */
    public function getMappingCollection(): ?MappingCollection
    {
        return $this->mapping ? $this->mapping[array_key_last($this->mapping)] : null;
    }

    /**
     * @return array An indexed array of the mapping collections used in all queries. Each element is an associative
     * array. The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getMappingCollectionHistory(): array
    {
        return $this->mapping;
    }

    /**
     * @return MappingCollection|null The config options used for the last query.
     */
    public function getConfig(): ?ConfigOptions
    {
        return $this->config ? $this->config[array_key_last($this->config)] : null;
    }

    /**
     * @return array An indexed array of the config options used in all queries. Each element is an associative
     * array. The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getConfigHistory(): array
    {
        return $this->config;
    }
}
