<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Marmalade\Objectiphy\IterableResult;
use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectFetcher
{
    private SqlBuilderInterface $sqlBuilder;
    private StorageInterface $storage;
    private ObjectBinder $objectBinder;
    private bool $bindToEntities = true;
    private bool $multiple;
    private bool $latest;
    private bool $onDemand;
    private string $keyProperty;
    
    public function __construct(SqlBuilder $sqlBuilder, StorageInterface $storage, ObjectBinder $objectBinder)
    {
        $this->sqlBuilder = $sqlBuilder;
        $this->storage = $storage;
        $this->objectBinder = $objectBinder;
        $this->setFindOptions(); //Set the defaults
    }

    /**
     * These are options that are likely to change on each call (unlike config options).
     */
    public function setFindOptions(
        bool $multiple = true, 
        bool $latest = false, 
        bool $onDemand = false, 
        string $keyProperty = ''
    ) {
        $this->multiple = $multiple;
        $this->latest = $latest;
        $this->onDemand = $onDemand;
        $this->keyProperty = $keyProperty;
    }

    /**
     * Any config options that the fetcher needs to know about are set here.
     */
    public function setConfigOptions(bool $bindToEntities)
    {
        $this->bindToEntities = $bindToEntities;
    }

    /**
     * @param string $className Parent entity class name.
     * @param array $criteria Associative array or array of CriteriaExpressions built by the QueryBuilder.
     * @param array $orderBy Key is the property (using dot notation for child objects), value is ASC or DESC.
     * @param PaginationInterface|null $pagination
     * @param string $scalarProperty Property name if returning a value or array of values from a single property.
     * @return mixed
     */
    public function doFindBy(
        string $className,
        array $criteria = [],
        array $orderBy = [],
        ?PaginationInterface $pagination = null,
        string $scalarProperty = ''
    ) {
        $this->validateCriteria();
        $this->doCount($pagination, $criteria);
        $result = $this->doFetch($criteria, $orderBy, $pagination, $scalarProperty);

        return $result;
    }

    /**
     * Ensure we have CriteriaExpressions in the criteria array (indicates that it has been normalised).
     * To save time, we won't check every element of the criteria array - if the first item is OK, the
     * rest will almost certainly be fine - not worth checking them all.
     * @param array $criteria
     */
    private function validateCriteria(array $criteria): void
    {
        if (!empty($criteria)
            && !(reset($criteria) instanceof CriteriaExpression)
            && !(reset($criteria) instanceof JoinExpression)
        ) {
            throw new CriteriaException('Invalid criteria passed to ObjectFetcher::doFindBy. If you are overriding a findBy method of a repository, please call $criteria = $this->normalizeCriteria($criteria) on your repository first.');
        }
    }

    /**
     * Count the records and populate the record count on the pagination object.
     */
    private function doCount(?PaginationInterface $pagination, array $criteria): void
    {
        if ($this->multiple && $pagination) {
            $countSql = $this->sqlBuilder->getSelectQuery($criteria, $this->multiple, $this->latest, true);
            $recordCount = intval($this->fetchValue($countSql, $this->sqlBuilder->getQueryParams()));
            $pagination->setTotalRecords($recordCount);
        }
    }

    /**
     * Return the records, in whatever format is requested.
     */
    private function doFetch(array $criteria, array $orderBy, ?PaginationInterface $pagination, string $scalarProperty)
    {
        $this->sqlBuilder->setPagination($pagination);
        $this->sqlBuilder->setOrderBy($orderBy);
        $sql = $this->sqlBuilder->getSelectQuery($criteria, $this->multiple, $this->latest);
        $params = $this->sqlBuilder->getQueryParams();

        if ($this->multiple && $this->iterable && $scalarProperty) {
            $result = $this->fetchIterableValues($sql, $params);
        } elseif ($this->multiple && $this->iterable) {
            $result = $this->fetchIterableResult($sql, $params);
        } elseif ($this->multiple && $scalarProperty) {
            $result = $this->fetchValues($sql, $params);
        } elseif ($this->multiple) {
            $result = $this->fetchResults($sql, $params, $keyProperty);
        } elseif ($scalarProperty) {
            $result = $this->fetchValue($sql, $params);
        } else {
            $result = $this->fetchResult($sql, $params);
        }

        return $result;
    }

    /**
     * Fetch a single value for an SQL query
     * @return mixed
     */
    public function fetchValue(string $sql, array $params = null)
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $value = $this->storage->fetchValue();

        return $value;
    }

    /**
     * Fetch an indexed array of single values for an SQL query (one element for each record)
     */
    public function fetchValues(string $sql, array $params = null): array
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $values = $this->storage->fetchValues();

        return $values;
    }

    /**
     * Fetch a single result for an SQL query, optionally binding to an entity.
     * @param string $sql SQL Statement to execute.
     * @param array|null $params Parameter values to bind.
     * @return array|object|null Array of data or entity.
     * @throws ObjectiphyException
     */
    public function fetchResult(string $sql, array $params = null)
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $row = $this->storage->fetchResult();
        if ($this->bindToEntities) {
            $result = $row ? $this->objectBinder->bindRowToEntity($row, $this->repository->getEntityClassName()) : null;
        } else {
            $result = $row;
        }

        return $result;
    }

    /**
     * Fetch all results for an SQL query, optionally binding to entities.
     * @param string $sql SQL Statement to execute.
     * @param array|null $params Parameter values to bind.
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @return array Array of arrays of data or array of entities.
     */
    public function fetchResults(string $sql, array $params = null, string $keyProperty = null): array
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $rows = $this->storage->fetchResults();
        if ($rows && $this->bindToEntities) {
            $result = $this->objectBinder->bindRowsToEntities($rows, $this->repository->getEntityClassName(), $keyProperty);
        } else {
            $result = $rows;
        }

        return $result;
    }

    /**
     * Fetch an iterable result for an SQL query, that can be looped over outside the repository. This is used when you
     * need to pull back a large amount of data and getting it all in one go would exhaust the memory. Using an iterable
     * result, you can fetch one record at a time and stream it to a file (or wherever).
     * @param $sql string SQL statement to execute.
     * @param array|null $params Parameter values to bind.
     * @return IterableResult A result that can be iterated with foreach.
     */
    public function fetchIterableResult(string $sql, array $params = null): IterableResult
    {
        $storage = clone($this->storage); //In case further queries happen before we iterate
        $storage->executeQuery($sql, $params ?: [], true);

        if ($this->bindToEntities) {
            $this->objectBinder->setIsIterable(true);
            $result = new IterableResult($storage, $this->objectBinder, $this->repository->getEntityClassName());
        } else {
            $result = new IterableResult($storage);
        }

        return $result;
    }

    /**
     * Fetch an iterable result for an SQL query involving simple scalar values.
     * @param $sql string SQL statement to execute.
     * @param array|null $params Parameter values to bind.
     * @return IterableResult A result that can be iterated with foreach.
     */
    public function fetchIterableValues(string $sql, array $params = null): IterableResult
    {
        $storage = clone($this->storage); //In case further queries happen before we iterate
        $storage->executeQuery($sql, $params ?: [], true);
        $result = new IterableResult($storage, null, null, true);

        return $result;
    }
}
