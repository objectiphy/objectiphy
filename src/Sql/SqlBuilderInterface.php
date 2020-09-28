<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

interface QueryBuilderInterface
{
    /**
     * Get the query necessary to select the records that will be used to hydrate the given entity.
     * @param array $criteria An array of CriteriaExpression objects.
     * @param array $fetchOptions Array of options (eg. multiple, latest, keyProperty - see ObjectFetcher)
     * @return string|object The query to execute (return type must match whatever is expected by the storage class).
     */
    public function getSelectQuery(array $criteria = [], array $fetchOptions = []);

    /**
     * Return the parameter values to bind to the query. Where more than one query is involved, the index identifies
     * which one we are dealing with.
     * @param int|null $index Index of the query.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams($index = null);

    /**
     * Allow query parameters to be set (or cleared) manually.
     */
    public function setQueryParams(array $params = []);

    /**
     * Get the queries necessary to insert the given row.
     * @param array $row The row to insert.
     * @param bool $replace Whether or not to update the row if the primary key already exists.
     * @return array An array of queries to execute for inserting this record.
     */
    public function getInsertQueries(array $row, $replace = false);

    /**
     * Get the queries necessary to update the given row record.
     * @param string $entityClassName Name of the parent entity class for the record being updated (used to get the
     * primary key column).
     * @param array $row Row of data to update.
     * @param mixed $keyValue Value of primary key for record to update.
     * @return array An array of queries to execute for updating the entity.
     */
    public function getUpdateQueries($entityClassName, $row, $keyValue);

    /**
     * Get the query necessary to load the values of foreign keys (primary keys of children)
     * @param $childClassName
     * @param $parentKeyPropertyName
     * @param $parentKeyPropertyValue
     */
    public function getForeignKeysQuery($childClassName, $parentKeyPropertyName, $parentKeyPropertyValue);

    /**
     * @param string $entityClassName Class name of entity being removed
     * @param mixed $keyValue Value of primary key for record to delete.
     * @return array An array of queries to execute for removing the entity.
     */
    public function getDeleteQueries($entityClassName, $keyValue);

    /**
     * @param mixed $query Query with parameter placeholders
     * @param array $params Values that relate to the parameter placeholders (associative array)
     * @return string String representation of query, with parameters resolved
     */
    public function replaceTokens($query, $params);
}
