<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\StorageInterface;

/**
 * @author Stephen Rayner <stephen.rayner@marmalade.co.uk>
 * @author Russell Walker <rwalker.php@gmail.com>
 * Represents a result that can be iterated with a foreach loop to fetch records from the database one at a time. This
 * can be used to stream large quantities of data without running out of memory.
 */
class IterableResult implements \Iterator
{
    protected ?StorageInterface $storage;
    protected ?ObjectBinder $objectBinder;
    protected ?string $entityClassName;
    protected bool $fetchValues = false;
    /**
     * @var mixed
     */
    protected $current = null;
    protected ?int $key = -1;
    protected $query;
    protected array $params;

    /**
     * IterableResult constructor.
     * @param mixed $query Usually a string of SQL (but could be anything) that is passed to $storage to rewind (re-run)
     * @param StorageInterface $storage a data store that has already executed a query (each call to the next method
     * will fetch another result).
     * @param ObjectBinder|null $objectBinder If binding to entities, the object binder needs to be provided here. If
     * no object binder is supplied, data will be returned as a flat array.
     * @param string|null $entityClassName If hydrating entities, specify the entity class name.
     * @param bool $fetchValues Whether to just fetch a single value at a time (as opposed to a whole object or array)
     */
    public function __construct(
        $query,
        array $params,
        StorageInterface $storage,
        ObjectBinder $objectBinder = null,
        ?string $entityClassName = null,
        bool $fetchValues = false
    ) {
        $this->query = $query;
        $this->params = $params;
        $this->storage = $storage;
        $this->objectBinder = $objectBinder;
        $this->entityClassName = $entityClassName;
        $this->fetchValues = $fetchValues;
    }

    /**
     * Return the current record
     * @return mixed Can return any type.
     * @throws \Throwable
     */
    public function current()
    {
        if ($this->key < 0) {
            $this->next();
        }
        return $this->current;
    }

    /**
     * Move forward to next element
     * @return void
     * @throws \Throwable
     */
    public function next(): void
    {
        if ($this->fetchValues) {
            $row = $this->storage->fetchValue();
        } else {
            $row = $this->storage->fetchResult();
        }
        $this->current = $row && $this->objectBinder
            ? $this->objectBinder->bindRowToEntity($row, $this->entityClassName, [], null, false)
            : $row;

        $this->key++;
    }

    /**
     * Return the key of the current element
     * @return int record index on success, or null on failure.
     */
    public function key(): ?int
    {
        return $this->key;
    }

    /**
     * Checks if current position is valid
     * @return bool
     * Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        return (bool) $this->current;
    }

    /**
     * We cannot literally rewind, but we can re-execute the query, which has the same effect.
     * @throws \Throwable
     */
    public function rewind(): void
    {
        $this->storage->executeQuery($this->query, $this->params, true);
        $this->next();
    }

    /**
     * Close the connection to the database.
     */
    public function closeConnection(): void
    {
        $this->storage = null;
        $this->current = false;
        $this->key = null;
    }
}
