<?php

namespace Objectiphy\Objectiphy\Contract;

interface ExplanationInterface
{
    /**
     * Get the last query that was executed.
     * @param boolean $parameterise Whether or not to replace parameter tokens with their values
     * @return string The query (typically SQL), with parameters converted to values so that the string can be pasted
     * into a database GUI and executed without having to replace values manually (when Objectiphy executes the query,
     * it does NOT use this exact string, it uses prepared statements).
     */
    public function getQuery($parameterise = true): string;

    /**
     * Get all the queries that were executed, including counts.
     * @param boolean $parameterise Whether or not to replace parameter tokens with their values
     * @return array All of the queries that have been executed, with parameters converted to values so that the string
     * can be pasted into a database GUI and executed without having to replace values manually (when Objectiphy
     * executes the query, it does NOT use this exact string, it uses prepared statements).
     */
    public function getQueryHistory($parameterise = true): array;

    /**
     * @return array The parameters used in the last query as an associative array
     */
    public function getParams(): array;

    /**
     * @return array An indexed array of all the parameters used in all queries. Each element is an associative array.
     * The index of the outer array exactly matches that returned by getQueryHistory.
     */
    public function getParamHistory(): array;
}