<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Used to obtain information about how a request was handled (in particular, database queries that were executed).
 */
interface ExplanationInterface
{
    /**
     * Called by Objectiphy when a query has been prepared for execution.
     * @param QueryInterface $query
     * @param string $sql
     * @param array $params
     * @param MappingCollection|null $mappingCollection
     * @param ConfigOptions|null $config
     */
    public function addQuery(
        QueryInterface $query,
        string $sql,
        array $params,
        MappingCollection $mappingCollection = null,
        ConfigOptions $config = null
    ): void;

    /**
     * Get the last query that was executed.
     * @return QueryInterface|null
     */
    public function getQuery(): ?QueryInterface;

    /**
     * Get all the queries that were executed.
     * @return QueryInterface[] All of the queries that have been executed.
     */
    public function getQueryHistory(): array;

    /**
     * Get the last SQL query that was executed.
     * @param bool $parameterise Whether or not to replace parameter tokens with their values
     * @return string The SQL, with parameters converted to values so that the string can be pasted
     * into a database GUI and executed without having to replace values manually (when Objectiphy executes the query,
     * it does NOT use this exact string, it uses prepared statements).
     */
    public function getSql(bool $parameterise = true): string;

    /**
     * Get all the SQL queries that were executed.
     * @param bool $parameterise Whether or not to replace parameter tokens with their values
     * @return array All of the queries that have been executed, with parameters converted to values so that the string
     * can be pasted into a database GUI and executed without having to replace values manually (when Objectiphy
     * executes the query, it does NOT use this exact string, it uses prepared statements).
     */
    public function getSqlHistory(bool $parameterise = true): array;

    /**
     * @return array The parameters used in the last query as an associative array
     */
    public function getParams(): array;

    /**
     * @return array An indexed array of all the parameters used in all queries. Each element is an associative array.
     * The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getParamHistory(): array;

    /**
     * @return MappingCollection|null The mapping collection used for the last query.
     */
    public function getMappingCollection(): ?MappingCollection;

    /**
     * @return array An indexed array of the mapping collections used in all queries. Each element is an associative
     * array. The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getMappingCollectionHistory(): array;

    /**
     * @return MappingCollection|null The config options used for the last query.
     */
    public function getConfig(): ?ConfigOptions;

    /**
     * @return array An indexed array of the config options used in all queries. Each element is an associative
     * array. The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getConfigHistory(): array;
}