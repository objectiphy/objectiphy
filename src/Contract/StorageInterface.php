<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for a class that implements storage to and from a datastore (probably always by executing SQL queries using
 * PDO, but could hypothetically be some custom class that feeds bananas to monkeys at typewriters, or whatever - the
 * main thing is to keep it de-coupled).
 */
interface StorageInterface
{
    /**
     * @param mixed $query Whatever your storage engine needs to do stuff (typically an SQL statement as a string, but
     * could be a custom Banana class). Whatever data type is passed in though, should be capable of being coerced into
     * a string so it can be output in a debug trace or profiler (so if you use a custom class, please implement
     * __toString() on it).
     * @param array $params Any parameters that need to be bound to the query.
     * @param bool $iterable Whether or not we are planning to return an iterable response (as opposed to a normal
     * smash and grab).
     * @return bool Whether or not the query execution was successful.
     */
    public function executeQuery($query, array $params = [], bool $iterable = false): bool;

    /**
     * @param int $columnNumber If more than one column can be returned by the query, this specifies which one to use.
     * @return mixed Return a single value from the last executed query - if more than one value came back from the
     * query, the first will be returned unless you specify a column number. If more than one record came back from the
     * query, only the first record will be used.
     */
    public function fetchValue(int $columnNumber = 0);

    /**
     * @param int $columnNumber If more than one column can be returned by the query, this specifies which one to use.
     * @return array Return an indexed array of values from the last executed query - if more than one value came back
     * from the query, the first will be used unless you specify a column number. If more than one record came back from
     * the query, only the first record will be used.
     */
    public function fetchValues(int $columnNumber = 0): array;

    /**
     * @return array Return an array of values from a record returned by the last executed query, indexed by column name
     * - if more than one record came back, only the values in the first one will be returned.
     */
    public function fetchResult(): array;

    /**
     * @return array Return an array of records, each of which is an array of values as returned by the last executed
     * query, indexed by column name.
     */
    public function fetchResults(): array;

    /**
     * @return int Return the number of rows that were affected by the last executed query.
     */
    public function getAffectedRecordCount(): int;

    /**
     * @return mixed If the last executed query inserted a new record with an auto-generated key, the new key is
     * returned.
     */
    public function getLastInsertId();

    /**
     * @return mixed Returns the last query to be executed.
     */
    public function getQuery();

    /**
     * @return array Returns the parameters that were used on the last query to be executed
     */
    public function getParams(): array;

    /**
     * @return array Returns an array containing all the queries that have been executed (in the order they were
     * executed).
     */
    public function getQueryHistory(): array;

    /**
     * @return array Returns an array of arrays, each of which is the set of parameters that were used in the
     * corresponding query from the getQueryHistory method.
     */
    public function getParamHistory(): array;
}
