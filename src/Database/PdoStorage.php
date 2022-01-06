<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Exception\StorageException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Handle interaction with PDO.
 */
class PdoStorage implements StorageInterface, TransactionInterface
{
    private \PDO $pdo;
    private \PDOStatement $stm;
    private bool $transactionStarted = false;
    private int $transactionNestingLevel = 0;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * This is not part of StorageInterface, but allows access to the database connection (for backward compatibility
     * reasons). You should avoid calling this if possible, as it defeats the purpose of the abstraction!
     * @return \PDO
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * @return bool Whether or not the transaction is underway (regardless of whether this call started it or not).
     */
    public function beginTransaction(): bool
    {
        if (!$this->transactionStarted) {
            $this->pdo->beginTransaction();
            $this->transactionStarted = true;
        }
        $this->transactionNestingLevel++;

        return $this->transactionNestingLevel > 0;
    }

    /**
     * @return bool Whether or not the transaction was rolled back.
     */
    public function rollback(): bool
    {
        if ($this->transactionNestingLevel >= 1) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->transactionStarted = false;
            $this->transactionNestingLevel = 0;
        } else {
            return false;
        }

        return $this->transactionNestingLevel >= 0;
    }

    /**
     * @return bool Whether or not the transaction was committed (or pseudo-committed if nested).
     */
    public function commit(): bool
    {
        if ($this->transactionNestingLevel == 1) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            $this->transactionStarted = false;
        }
        $this->transactionNestingLevel--;

        return $this->transactionNestingLevel >= 0;
    }

    /**
     * @param mixed $query SQL string (or whatever) to execute
     * @param array $params Any parameters that need to be bound to the query.
     * @param bool $iterable Whether or not we are planning to return an iterable response (as opposed to a normal
     * smash and grab).
     * @return bool Whether or not the query execution was successful.
     * @throws StorageException
     */
    public function executeQuery($query, array $params = [], $iterable = false): bool
    {
        try {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !$iterable);
            $this->stm = $this->pdo->prepare($query);
            $success = $this->stm->execute($params);
        } catch (\Throwable $ex) {
            throw new StorageException('PDO Error: ' . $ex->getMessage() . ' (' . $query . ') ' . print_r($params, true), intval($ex->getCode()), $ex);
        }

        $pdoError = $this->stm->errorInfo();
        if (intval($this->stm->errorCode()) != 0) {
            //If in silent mode, throw an exception so we can rollback
            $errorMessage = isset($pdoError[2]) ? $pdoError[2] : 'Unknown error';
            $sqlStateCode = isset($pdoError[0]) ? $pdoError[0] : 0;
            $errorCode = isset($pdoError[1]) ? $pdoError[1] : 0;

            throw new StorageException($errorMessage . ' (SQL State Code: ' . $sqlStateCode . ')', $errorCode);
        }

        return $success;
    }

    /**
     * @param int $columnNumber If more than one column can be returned by the query, this specifies which one to use.
     * @return mixed Return a single value from the last executed query - if more than one value came back from the
     * query, the first will be returned unless you specify a column number. If more than one record came back from the
     * query, only the first record will be used.
     */
    public function fetchValue(int $columnNumber = 0)
    {
        $value = null;
        if (isset($this->stm)) {
            $value = $this->stm->fetchColumn($columnNumber);
        }

        return $value;
    }

    /**
     * @param int $columnNumber If more than one column can be returned by the query, this specifies which one to use.
     * @return array Return an indexed array of values from the last executed query - if more than one value came back
     * from the query, the first will be used unless you specify a column number. If more than one record came back from
     * the query, only the first record will be used.
     */
    public function fetchValues(int $columnNumber = 0): array
    {
        $values = [];
        if (isset($this->stm)) {
            $values = $this->stm->fetchAll(\PDO::FETCH_COLUMN, $columnNumber);
        }

        return $values ?: [];
    }

    /**
     * @return array Return an array of values from a record returned by the last executed query, indexed by column name
     * - if more than one record came back, only the values in the first one will be returned.
     */
    public function fetchResult(): array
    {
        $result = [];
        if (isset($this->stm)) {
            $result = $this->stm->fetch(\PDO::FETCH_ASSOC);
        }

        return $result ?: [];
    }

    /**
     * @return array Return an array of records, each of which is an array of values as returned by the last executed
     * query, indexed by column name.
     */
    public function fetchResults(): array
    {
        $results = [];
        if (isset($this->stm)) {
            $results = $this->stm->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $results ?: [];
    }

    /**
     * @return int Return the number of rows that were affected by the last executed query.
     */
    public function getAffectedRecordCount(&$duplicateUpdated = false): int
    {
        $affectedRows = 0;
        if (isset($this->stm)) {
            $affectedRows = $this->stm->rowCount();
            if ($affectedRows > 1 && strpos($this->stm->queryString, 'ON DUPLICATE KEY') !== false) {
                //Updated records return 2, not 1
                $affectedRows = intval($affectedRows / 2);
                $duplicateUpdated = true;
            }
        }

        return intval($affectedRows);
    }

    /**
     * @return mixed If the last executed query inserted a new record with an auto-generated key, the new key is
     * returned.
     */
    public function getLastInsertId()
    {
        $lastInsertId = $this->pdo->lastInsertId();

        return intval($lastInsertId) == $lastInsertId ? intval($lastInsertId) : $lastInsertId;
    }
}
