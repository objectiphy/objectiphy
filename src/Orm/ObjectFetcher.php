<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\QB;

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
    private SqlStringReplacer $stringReplacer;
    private FindOptions $options;
    private ConfigOptions $configOptions;
    private ExplanationInterface $explanation;

    public function __construct(
        SqlSelectorInterface $sqlSelector,
        ObjectMapper $objectMapper,
        ObjectBinder $objectBinder,
        StorageInterface $storage,
        EntityTracker $entityTracker,
        SqlStringReplacer $stringReplacer,
        ExplanationInterface $explanation
    ) {
        $this->sqlSelector = $sqlSelector;
        $this->storage = $storage;
        $this->objectMapper = $objectMapper;
        $this->objectBinder = $objectBinder;
        $this->entityTracker = $entityTracker;
        $this->stringReplacer = $stringReplacer;
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
        $this->options->mappingCollection->setGroups(
            $this->configOptions->hydrateUngroupedProperties,
            ...$this->configOptions->serializationGroups
        );
        $queryClass = $query->getFrom();
        if ($queryClass && strpos($queryClass, '`') === false) { //No explicit table specified
            $originalClass = $this->getClassName();
            $this->setClassName($query->getFrom());
        }
        if ($this->options->scalarProperty) {
            $query->setSelect(new FieldExpression($this->options->scalarProperty));
        }
        $query->selectPrimaryKeys($this->options->mappingCollection);
        $extraMappingsAdded = false;
        $extraMappingsAdded = $this->objectMapper->addExtraMappings($this->getClassName(), $this->options);
        $extraMappingsAdded = $this->objectMapper->addExtraMappings($this->getClassName(), $query) || $extraMappingsAdded;
        $this->objectMapper->addExtraClassMappings($this->getClassName(), $query);
        if ($this->options->indexBy) {
            $indexProperty = $this->options->mappingCollection->getPropertyMapping($this->options->indexBy);
            if ($indexProperty) { //If indexing by a column or expression, there won't be a mapping for it
                $indexProperty->forceFetchable();
            }
        }
        $this->options->mappingCollection->getRelationships(true, $extraMappingsAdded); //Ensures all mapping is populated even if mapped by other side
        $query->finalise($this->options->mappingCollection, $this->stringReplacer, null);
        if ($this->options->indexBy) {
            $indexByField = new FieldExpression($this->options->indexBy);
            $indexByField->setAlias('objectiphy_index_by');
            $query->setSelect(...array_merge($query->getSelect(), [$indexByField]));
        }
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
     * @param bool $clearMappingCache Whether or not to also clear the mapping information.
     */
    public function clearCache(?string $className = null, bool $clearMappingCache = false): void
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
        if ($this->options->indexBy ?? false) {
            $values = $this->storage->fetchResults();
            return $values ? $this->indexValues($values, array_key_first($values[0])) : $values;
        } else {
            return $this->storage->fetchValues(0);
        }
    }

    /**
     * Fetch a single result for an SQL query, optionally binding to an entity.
     * @param string $sql SQL Statement to execute.
     * @param array|null $params Parameter values to bind.
     * @return array|object|null Array of data or entity.
     * @throws ObjectiphyException|\Throwable
     */
    public function fetchResult(string $sql, array $params = null, ?bool $bindToEntitiesOverride = null)
    {
        $bindToEntities = !is_null($bindToEntitiesOverride) || !$this->options ? boolval($bindToEntitiesOverride) : $this->options->bindToEntities;
        $this->storage->executeQuery($sql, $params ?: []);
        $row = $this->storage->fetchResult();
        if ($bindToEntities) {
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
     * @param bool|null $bindToEntitiesOverride For backward compatibility only.
     * @return array Array of arrays of data or array of entities.
     * @throws MappingException|\Throwable
     */
    public function fetchResults(string $sql, array $params = null, ?string $keyProperty = null, ?bool $bindToEntitiesOverride = null): array
    {
        if (!is_null($keyProperty)) {
            $this->options->indexBy = $keyProperty;
        }
        $bindToEntities = !is_null($bindToEntitiesOverride) || !$this->options ? boolval($bindToEntitiesOverride) : $this->options->bindToEntities;
        $this->storage->executeQuery($sql, $params ?: []);
        $rows = $this->storage->fetchResults();
        if ($rows && $bindToEntities) {
            $result = $this->objectBinder->bindRowsToEntities($rows, $this->getClassName(), $this->options->indexBy);
        } else {
            $result = $this->indexValues($rows);
        }

        return $result;
    }

    private function indexValues(array $rows, string $valueKey = '')
    {
        if ($this->options->indexBy) {
            $results = [];
            foreach ($rows ?? [] as $index => $row) {
                $key = $row['objectiphy_index_by'] ?? $row[$this->options->indexBy] ?? $index;
                if (is_array($row) && isset($row['objectiphy_index_by'])) {
                    unset($row['objectiphy_index_by']); //Internal use only, and we've finished with it now
                }
                $results[$key] = $valueKey ? $row[$valueKey] : $row;
            }
            return $results;
        } else {
            return $rows;
        }
    }

    /**
     * Fetch an iterable result for an SQL query, that can be looped over outside the repository. This is used when you
     * need to pull back a large amount of data and getting it all in one go would exhaust the memory. Using an iterable
     * result, you can fetch one record at a time and stream it to a file (or wherever).
     * @param $sql string SQL statement to execute.
     * @param array|null $params Parameter values to bind.
     * @return IterableResult A result that can be iterated with foreach.
     */
    public function fetchIterableResult(string $sql, array $params = null, ?bool $bindToEntitiesOverride = null): IterableResult
    {
        $bindToEntities = !is_null($bindToEntitiesOverride) || !$this->options ? boolval($bindToEntitiesOverride) : $this->options->bindToEntities;
        $storage = clone($this->storage); //In case further queries happen before we iterate
        $storage->executeQuery($sql, $params ?: [], true);
        if ($bindToEntities) {
            $result = new IterableResult($sql, $params ?: [], $storage, $this->objectBinder, $this->getClassName());
        } else {
            $result = new IterableResult($sql, $params ?: [], $storage);
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

        return new IterableResult($sql, $params ?: [], $storage, null, null, true);
    }

    /**
     * @param SelectQueryInterface $query
     */
    public function inferFindOptionsFromQuery(SelectQueryInterface $query, MappingCollection $mappingCollection)
    {
        //If criteria includes primary key with = operator and has no ORs, or limit = 1 there can only be one record
        if ($query->getLimit() != 1) {
            $pkWithEquals = false;
            $orsPresent = false;
            $pkProperties = $mappingCollection->getPrimaryKeyProperties();
            foreach ($query->getWhere() as $criteria) {
                if ($criteria instanceof CriteriaGroup) {
                    $orsPresent = $criteria->type == CriteriaGroup::GROUP_TYPE_START_OR;
                } elseif ($criteria instanceof CriteriaExpression) {
                    $orsPresent = $orsPresent || $criteria->joiner == CriteriaExpression::JOINER_OR;
                    $propertyName = str_replace('%', '', $criteria->property->getExpression());
                    $pkWithEquals = in_array($propertyName, $pkProperties) && $criteria->operator == QB::EQ;
                }
            }
        }

        //If select part is populated and has no pure property paths, we cannot bind to entities
        $selectCount = count($query->getSelect());
        $hasPureProperties = false;
        if ($selectCount) {
            foreach ($query->getSelect() as $fieldExpression) {
                if ($fieldExpression->isPropertyPath()) {
                    $hasPureProperties = true;
                }
            }
        }

        //If select part is populated and there is only one item in it, which is not a pure property, return values
        $scalarProperty = '';
        if ($selectCount == 1 && !$hasPureProperties) {
            $scalarProperty = $fieldExpression->getExpression();
        }

        $findOptions = FindOptions::create($mappingCollection, [
            'multiple' => !($query->getLimit() == 1 || ($pkWithEquals && !$orsPresent)),
            'bindToEntities' => !$selectCount || $hasPureProperties,
            'scalarProperty' => $scalarProperty,
        ]);

        return $findOptions;
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
            $this->explanation->addQuery($query, $countSql, $this->options->mappingCollection, $this->configOptions);
            $recordCount = intval($this->fetchValue($countSql, $query->getParams()));
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
        if (empty($query->getFields())) {
            //If we are late binding, just return null (or empty array) - otherwise throw up
            $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $functions = array_column($backtrace, 'function');
            if (in_array('bindRowToEntity', $functions) || in_array('triggerLazyLoad', $functions)) {
                return $this->options->multiple ? [] : null;
            }
            throw new QueryException('There are no fields to select! Did you use the wrong Serialization Group name?');
        }
        $sql = $this->sqlSelector->getSelectSql($query);
        $this->explanation->addQuery($query, $sql, $this->options->mappingCollection, $this->configOptions);
        if ($this->options->multiple && $this->options->onDemand && $this->options->scalarProperty) {
            $result = $this->fetchIterableValues($sql, $query->getParams());
        } elseif ($this->options->multiple && $this->options->onDemand) {
            $result = $this->fetchIterableResult($sql, $query->getParams());
        } elseif ($this->options->multiple && $this->options->scalarProperty) {
            $result = $this->fetchValues($sql, $query->getParams());
        } elseif ($this->options->multiple) {
            $result = $this->fetchResults($sql, $query->getParams());
        } elseif ($this->options->scalarProperty) {
            $result = $this->fetchValue($sql, $query->getParams());
        } else {
            $result = $this->fetchResult($sql, $query->getParams());
        }

        return $result;
    }
}
