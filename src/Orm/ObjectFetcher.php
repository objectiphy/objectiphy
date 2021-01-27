<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
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
    private ConfigOptions $configOptions;
    private ExplanationInterface $explanation;
    
    public function __construct(
        SqlSelectorInterface $sqlSelector,
        ObjectMapper $objectMapper,
        ObjectBinder $objectBinder,
        StorageInterface $storage,
        EntityTracker $entityTracker,
        ExplanationInterface $explanation
    ) {
        $this->sqlSelector = $sqlSelector;
        $this->storage = $storage;
        $this->objectMapper = $objectMapper;
        $this->objectBinder = $objectBinder;
        $this->entityTracker = $entityTracker;
        $this->explanation = $explanation;
    }

    /**
     * Exposing our private parts :o
     * @return StorageInterface
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * @param ConfigOptions $configOptions
     * @throws ObjectiphyException
     */
    public function setConfigOptions(ConfigOptions $configOptions): void
    {
        $this->configOptions = $configOptions;
        $this->objectBinder->setConfigOptions($configOptions);
    }

    /**
     * Config options relating to fetching data only.
     * @param FindOptions $findOptions
     */
    public function setFindOptions(FindOptions $findOptions): void
    {
        $this->options = $findOptions;
        $this->sqlSelector->setFindOptions($findOptions);
        $this->objectBinder->setMappingCollection($findOptions->mappingCollection);
    }

    /**
     * No need to bind these as we already know the values (typically used to pre-populate the parent)
     * @param array $knownValues
     */
    public function setKnownValues(array $knownValues)
    {
        $this->objectBinder->setKnownValues($knownValues);
    }

    /**
     * If we already have this entity in the tracker, return it.
     * @param string $className
     * @param $pkValues
     * @return object|null
     */
    public function getExistingEntity(string $className, $pkValues): ?object
    {
        if (!is_iterable($pkValues)) {
            $pkValues = [$pkValues];
        }
        
        return $this->entityTracker->getEntity($className, $pkValues);
    }

    /**
     * @param SelectQueryInterface $query
     * @return mixed
     * @throws ObjectiphyException
     * @throws \ReflectionException|\Throwable
     */
    public function executeFind(SelectQueryInterface $query) 
    {
        $this->validate();
        $queryClass = $query->getFrom();
        if ($queryClass && strpos($queryClass, '`') === false) { //No explicit table specified
            $originalClass = $this->getClassName();
            $this->setClassName($query->getFrom());            
        }
        $this->objectMapper->addExtraMappings($this->getClassName(), $this->options);
        $this->objectMapper->addExtraMappings($this->getClassName(), $query);
        if ($this->options->keyProperty) {
            $this->options->mappingCollection->forceFetch($this->options->keyProperty);
        }
        $query->finalise($this->options->mappingCollection);
        $this->doCount($query);
        $result = $this->doFetch($query);
        if (isset($originalClass)) {
            $this->setClassName($originalClass);
        }

        return $result;
    }

    /**
     * Clear the entity tracker to ensure objects get refreshed from the database
     * @param string|null $className
     * @param bool $clearMappingCache Whether or not to also clear the mapping information (only useful when the
     * mapping information is being overridden).
     */
    public function clearCache(?string $className = null, bool $clearMappingCache = true): void
    {
        $this->entityTracker->clear($className);
        if ($clearMappingCache) {
            $this->objectMapper->clearMappingCache($className);
            $this->objectBinder->clearMappingCache($className); //Has factory which uses separate mapper for late binding
        }
    }

    /**
     * Fetch a single value for an SQL query
     * @param string $sql
     * @param array|null $params
     * @return mixed
     */
    public function fetchValue(string $sql, array $params = null)
    {
        $this->storage->executeQuery($sql, $params ?: []);
        return $this->storage->fetchValue();
    }

    /**
     * Fetch an indexed array of single values for an SQL query (one element for each record)
     * @param string $sql
     * @param array|null $params
     * @return array
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
     * @throws ObjectiphyException|\Throwable
     */
    public function fetchResult(string $sql, array $params = null)
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $row = $this->storage->fetchResult();
        if ($this->options->bindToEntities) {
            $className = $this->getClassName();
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
     * @return array Array of arrays of data or array of entities.
     * @throws MappingException|\Throwable
     */
    public function fetchResults(string $sql, array $params = null): array
    {
        $this->storage->executeQuery($sql, $params ?: []);
        $rows = $this->storage->fetchResults();
        if ($rows && $this->options->bindToEntities) {
            $result = $this->objectBinder->bindRowsToEntities($rows, $this->getClassName(), $this->options->keyProperty);
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

        if ($this->options->bindToEntities) {
            $result = new IterableResult($storage, $this->objectBinder, $this->getClassName());
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
        return new IterableResult($storage, null, null, true);
    }

    private function getClassName(): string
    {
        if (isset($this->options) && isset($this->options->mappingCollection)) {
            return $this->options->mappingCollection->getEntityClassName();
        }

        return '';
    }

    private function setClassName(string $className): void
    {
        $this->options->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $this->setFindOptions($this->options); //To ensure everyone is kept informed
    }

    /**
     * Ensure find options have been set.
     * @throws ObjectiphyException
     */
    private function validate(): void
    {
        if (empty($this->options)) {
            throw new ObjectiphyException('Find options have not been set on the object fetcher.');
        }
    }

    /**
     * Count the records and populate the record count on the pagination object.
     * @param SelectQueryInterface $query
     */
    private function doCount(SelectQueryInterface $query): void
    {
        if ($this->options->multiple && $this->options->pagination) {
            $this->options->count = true;
            $countSql = $this->sqlSelector->getSelectSql($query);
            $params = $this->sqlSelector->getQueryParams();
            $this->explanation->addQuery($query, $countSql, $params, $this->options->mappingCollection, $this->configOptions);
            $recordCount = intval($this->fetchValue($countSql, $params));
            $this->options->pagination->setTotalRecords($recordCount);
            $this->options->count = false;
        }
    }

    /**
     * Return the records, in whatever format is requested.
     * @param SelectQueryInterface $query
     * @return array|mixed|object|IterableResult|null
     * @throws ObjectiphyException|\Throwable
     */
    private function doFetch(SelectQueryInterface $query)
    {
        $sql = $this->sqlSelector->getSelectSql($query);
        $params = $this->sqlSelector->getQueryParams();
        $this->explanation->addQuery($query, $sql, $params, $this->options->mappingCollection, $this->configOptions);
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
}
