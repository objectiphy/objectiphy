<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Meta;

use Objectiphy\Objectiphy\Contract\ExplanationInterface;

/**
 * THIS IS FOR DEBUGGING/PROFILING PURPOSES ONLY! Do not ever use the output of this class to execute database queries 
 * via code or in production, as it deliberately does not sanitise user input. It is ONLY to be used to help you 
 * understand what is going on under the hood.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class Explanation implements ExplanationInterface
{
    private array $queryHistory = [];
    private array $paramHistory = [];

    /**
     * Used internally to record query activity - not part of the interface
     * @param string $query
     * @param array $params
     */
    public function addQuery(string $query, array $params): void
    {
        $this->queryHistory[] = $query;
        $this->paramHistory[] = $params;
    }

    /**
     * Get the last query that was executed.
     * @param boolean $parameterise Whether or not to replace parameter tokens with their values
     * @return string The query (typically SQL), with parameters converted to values so that the string can be pasted
     * into a database GUI and executed without having to replace values manually (when Objectiphy executes the query,
     * it does NOT use this exact string, it uses prepared statements).
     */
    public function getQuery(bool $parameterise = true): string
    {
        if ($this->queryHistory) {
            $lastQuery = $this->queryHistory[array_key_last($this->queryHistory)];
            $lastParams = $this->getParams();

            return $parameterise ? $this->replaceTokens($lastQuery, $lastParams) : $lastQuery;
        }

        return '';
    }

    /**
     * Get all the queries that were executed, including counts.
     * @param boolean $parameterise Whether or not to replace parameter tokens with their values
     * @return array All of the queries that have been executed, with parameters converted to values so that the string
     * can be pasted into a database GUI and executed without having to replace values manually (when Objectiphy
     * executes the query, it does NOT use this exact string, it uses prepared statements).
     */
    public function getQueryHistory(bool $parameterise = true): array
    {
        $queryHistory = $this->queryHistory;
        if ($parameterise) {
            array_walk($queryHistory, function (&$query, $index, $paramHistory = []) {
                $query = $this->replaceTokens($query, $paramHistory[$index]);
            }, $paramHistory);
        }

        return $queryHistory ?: [];
    }

    /**
     * @return array The parameters used in the last query as an associative array
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
     * Replace prepared statement parameters with actual values (for debugging output only, not for execution!)
     * @param string $query Parameterised query string
     * @param array $params Parameter values to replace tokens with
     * @return string Query string with values instead of parameters
     */
    private function replaceTokens(string $query, array $params): string
    {
        if (count($params)) {
            foreach (array_reverse($params) as $key => $value) { //Don't want to replace param_10 with column name for param_1 followed by a zero!
                $resolvedValue = $value === null || $value === true || $value === false ? var_export($value, true) : "'$value'";
                $query = str_replace(':' . $key, $resolvedValue, $query);
            }
        }

        return $query;
    }
}
