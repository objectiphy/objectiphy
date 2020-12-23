<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Marmalade\Objectiphy\IterableResult;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectFetcher
{
    private SqlSelectorInterface $sqlSelector;
    private StorageInterface $storage;
    private ObjectMapper $objectMapper;
    private ObjectBinder $objectBinder;
    private EntityTracker $entityTracker;
    private FindOptions $options;
    
    public function __construct(
        SqlSelectorInterface $sqlSelector,
        ObjectMapper $objectMapper,
        ObjectBinder $objectBinder,
        StorageInterface $storage,
        EntityTracker $entityTracker
    ) {
        $this->sqlSelector = $sqlSelector;
        $this->storage = $storage;
        $this->objectMapper = $objectMapper;
        $this->objectBinder = $objectBinder;
        $this->entityTracker = $entityTracker;
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Config options relating to fetching data only.
     */
    public function setFindOptions(FindOptions $findOptions): void
    {
        $this->options = $findOptions;
        $this->sqlSelector->setFindOptions($findOptions);
        $this->objectBinder->setMappingCollection($findOptions->mappingCollection);
    }

    public function setConfigOptions(ConfigOptions $configOptions): void
    {
        $this->objectBinder->setConfigOptions($configOptions);
    }

    public function getExistingEntity(string $className, $pkValues): ?object
    {
        if (!is_array($pkValues)) {
            $pkValues = [$pkValues];
        }
        
        return $this->entityTracker->getEntity($className, $pkValues);
    }

    /**
     * @return mixed
     */
    public function executeFind(SelectQueryInterface $query) 
    {
        $this->validate();
        if ($query->getFrom()) {
            $originalClass = $this->options->mappingCollection->getEntityClassName();
            $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($query->getFrom());
            $this->setFindOptions($this->options); //To ensure everyone is kept informed
        }
        $this->objectMapper->addExtraMappings($this->options->getClassName(), $this->options);
        $this->objectMapper->addExtraMappings($this->options->getClassName(), $query);
        if ($this->options->keyProperty) {
            $this->options->mappingCollection->forceFetch($this->options->keyProperty);
        }
        $query->finalise($this->options->mappingCollection);
        $this->doCount($query);
        $result = $this->doFetch($query);
        if (isset($originalClass)) {
            $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($originalClass);
            $this->setFindOptions($this->options);
        }

        return $result;
    }

    /**
     * Clear the entity tracker to ensure objects get refreshed from the database
     * @param string|null $className
     */
    public function clearCache(?string $className = null, bool $forgetChangesOnly = false): void
    {
        $this->entityTracker->clear($className, $forgetChangesOnly);
    }

    /**
     * Ensure find options have been set.
     */
    private function validate(): void
    {
        if (empty($this->options)) {
            throw new ObjectiphyException('Find options have not been set on the object fetcher.');
        }
    }

    /**
     * Count the records and populate the record count on the pagination object.
     */
    private function doCount(SelectQueryInterface $query): void
    {
        if ($this->options->multiple && $this->options->pagination) {
            $this->options->count = true;
            $countSql = $this->sqlSelector->getSelectSql($query);
            $recordCount = intval($this->fetchValue($countSql, $this->sqlSelector->getQueryParams()));
            $this->options->pagination->setTotalRecords($recordCount);
            $this->options->count = false;
        }
    }

    /**
     * Return the records, in whatever format is requested.
     */
    private function doFetch(SelectQueryInterface $query)
    {
        $sql = $this->sqlSelector->getSelectSql($query);
        $params = $this->sqlSelector->getQueryParams();

        if ($this->options->multiple && $this->options->onDemand && $this->options->scalarProperty) {
            $result = $this->fetchIterableValues($sql, $params);
        } elseif ($this->options->multiple && $this->options->onDemand) {
            $result = $this->fetchIterableResult($sql, $params);
        } elseif ($this->options->multiple && $this->options->scalarProperty) {
            $result = $this->fetchValues($sql, $params);
        } elseif ($this->options->multiple) {
            $result = $this->fetchResults($sql, $params);
        } elseif ($this->options->scalarProperty) {
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
        if ($this->options->bindToEntities) {
            $className = $this->options->mappingCollection->getEntityClassName();
            $result = $row ? $this->objectBinder->bindRowToEntity($row, $className) : null;
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
    public function fetchResults(string $sql, array $params = null): array
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $rows = $this->storage->fetchResults();
        if ($rows && $this->options->bindToEntities) {
            $result = $this->objectBinder->bindRowsToEntities($rows, $this->options->getClassName(), $this->options->keyProperty);
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
