<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\InsertQueryInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\ObjectRepositoryInterface;
use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Factory\ProxyFactory;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\Pagination;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\SelectQuery;

/**
 * Main entry point for all ORM operations
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class ObjectRepository implements ObjectRepositoryInterface, TransactionInterface
{
    protected ConfigOptions $configOptions;
    protected RepositoryFactory $repositoryFactory;
    protected ObjectMapper $objectMapper;
    protected ObjectFetcher $objectFetcher;
    protected ObjectPersister $objectPersister;
    protected ObjectRemover $objectRemover;
    protected ProxyFactory $proxyFactory;
    protected ?PaginationInterface $pagination = null;
    protected array $orderBy;
    protected MappingCollection $mappingCollection;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        ObjectMapper $objectMapper,
        ObjectFetcher $objectFetcher,
        ObjectPersister $objectPersister,
        ObjectRemover $objectRemover,
        ProxyFactory $proxyFactory,
        ConfigOptions $configOptions = null
    ) {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectMapper = $objectMapper;
        $this->objectFetcher = $objectFetcher;
        $this->objectPersister = $objectPersister;
        $this->objectPersister->setRepository($this);
        $this->objectRemover = $objectRemover;
        $this->proxyFactory = $proxyFactory;
        if (!$configOptions) {
            $configOptions = new ConfigOptions();
        }
        $this->setConfiguration($configOptions);
    }

    /**
     * @param ConfigOptions $configOptions
     */
    public function setConfiguration(ConfigOptions $configOptions): void
    {
        $this->configOptions = $configOptions;
        $this->updateConfig();
    }
    
    public function getConfiguration(): ConfigOptions
    {
        return $this->configOptions;
    }

    /**
     * Set a general configuration option by name. Available options are defined on
     * the Objectiphy\Objectiphy\Config\ConfigOptions class.
     * @param string $optionName
     * @param $value
     * @return mixed The previously set value (or default value if not previously set).
     */
    public function setConfigOption(string $optionName, $value)
    {
        $previousValue = $this->configOptions->setConfigOption($optionName, $value);
        $this->updateConfig();
        
        return $previousValue;
    }

    /**
     * Set an entity-specific configuration option by name. Available options are 
     * defined on the Objectiphy\Objectiphy\Config\ConfigEntity class.
     * @param string $entityClassName
     * @param string $optionName
     * @param $value
     */
    public function setEntityConfigOption(string $entityClassName, string $optionName, $value): void
    {
        $entityConfigs = $this->configOptions->getConfigOption('entityConfig');
        $entityConfig = $entityConfig[$entityClassName] ?? new ConfigEntity();
        $entityConfig->setConfigOption($optionName, $value);
        $entityConfigs[$entityClassName] = $entityConfig;
        $this->setConfigOption('entityConfig', $entityConfigs);
    }

    /**
     * Setter for the parent entity class name.
     * @param string $className
     */
    public function setClassName(string $className): void
    {
        if ($className != $this->getClassName()) {
            $this->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
            $this->orderBy = [];
            //In case of custom repository that does not pass along the find/save options, set defaults here
            $findOptions = FindOptions::create($this->mappingCollection);
            $this->objectFetcher->setFindOptions($findOptions);
            $saveOptions = SaveOptions::create($this->mappingCollection);
            $this->objectPersister->setSaveOptions($saveOptions);
        }
    }

    /**
     * Getter for the parent entity class name.
     * @return string
     */
    public function getClassName(): string
    {
        if (isset($this->mappingCollection)) {
            return $this->mappingCollection->getEntityClassName();
        }

        return '';
    }

    /**
     * Set a pagination object (to store and supply information about how the results are paginated)
     * (can be set to null to remove previously set pagination)
     * @param PaginationInterface
     */
    public function setPagination(?PaginationInterface $pagination): void
    {
        $this->pagination = $pagination;
    }

    /**
     * @param array $orderBy Key = property name, value = ASC or DESC.
     */
    public function setOrderBy(array $orderBy): void
    {
        $this->orderBy = $orderBy;
    }

    /**
     * Find a single record (and hydrate it as an entity) with the given primary key value. Compatible with the
     * equivalent method in Doctrine.
     * @param mixed $id Primary key value - for composite keys, can be an array
     * @return object|array|null
     */
    public function find($id)
    {
        $this->assertClassNameSet();
        $existingEntity = $this->objectFetcher->getExistingEntity($this->getClassName(), $id);
        if ($existingEntity) {
            return $existingEntity;
        }
        $pkProperties = $this->mappingCollection->getPrimaryKeyProperties();
        if (!$pkProperties) {
            $errorMessage = sprintf(
                'The current entity (`%1$s`) does not have a primary key, so you cannot use the find method. Either specify a primary key in the mapping information, or use findOneBy instead.',
                $this->getClassName()
            );
            $this->throwException(new ObjectiphyException($errorMessage));
        }

        return $this->findOneBy(array_combine($pkProperties, is_array($id) ?: [$id]));
    }

    /**
     * Find a single record (and hydrate it as an entity) for the given criteria. Compatible with the equivalent method
     * in Doctrine.
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @return object|array|null
     */
    public function findOneBy($criteria = [])
    {
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => false,
            'bindToEntities' => $this->configOptions->bindToEntities,
        ]);

        return $this->doFindBy($findOptions, $criteria);
    }

    /**
     * Return the latest record from a group
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param string|null $commonProperty Property on root entity whose value you want to group by (see also the
     * setCommonProperty method).
     * @param string|null $recordAgeIndicator Fully qualified database column or expression that determines record age
     * (see also the setCommonProperty method).
     * @return object|array|null
     */
    public function findLatestOneBy(
        $criteria = [],
        ?string $commonProperty = null,
        ?string $recordAgeIndicator = null
    ) {

        //TODO: common property/age indicator

        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => false,
            'latest' => true,
            'bindToEntities' => $this->configOptions->bindToEntities,
        ]);

        return $this->doFindBy($findOptions, $criteria);
    }

    /**
     * Return the latest record from each group
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param string|null $commonProperty Property on root entity whose value you want to group by (see also the
     * setCommonProperty method).
     * @param string|null $recordAgeIndicator Fully qualified database column or expression that determines record age
     * (see also the setCommonProperty method).
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param boolean $multiple For internal use (when this method is called by the findLatestOneBy method).
     * @param boolean $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return iterable
     */
    public function findLatestBy(
        $criteria = [],
        ?string $commonProperty = null,
        ?string $recordAgeIndicator = null,
        ?string $keyProperty = null,
        bool $multiple = true,
        bool $fetchOnDemand = false
    ): ?iterable {
        return null;
    }

    /**
     * Find all records that match the given criteria (and hydrate them as entities). Compatible with the equivalent
     * method in Doctrine.
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param array|null $orderBy Array of properties and optionally sort directions (eg. ['child.name'=>'DESC',
     * 'date']).
     * @param int|null $limit Only here to provide compatibility with Doctrine - normally we would use setPagination.
     * @param int|null $offset Only here to provide compatibility with Doctrine - normally we would use setPagination.
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param boolean $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set (for streaming large amounts of data).
     * @return array|object|null
     */
    public function findBy(
        $criteria = [],
        ?array $orderBy = null,
        $limit = null,
        $offset = null,
        ?string $keyProperty = null,
        bool $fetchOnDemand = false
    ): ?iterable {
        $this->setOrderBy(array_filter($orderBy ?? $this->orderBy ?? []));
        if ($limit) { //Only for Doctrine compatibility
            $this->pagination = new Pagination($limit, round($offset / $limit) + 1);
        }
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => true,
            'orderBy' => $this->orderBy,
            'keyProperty' => $keyProperty ?? '',
            'onDemand' => $fetchOnDemand,
            'pagination' => $this->pagination ?? null,
            'bindToEntities' => $this->configOptions->bindToEntities,
        ]);

        return $this->doFindBy($findOptions, $criteria);
    }

    /**
     * Alias for findBy but automatically sets the $fetchOnDemand flag to true and avoids needing to supply null values
     * for the arguments that are not applicable (findBy thus remains compatible with Doctrine).
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param array|null $orderBy
     * @return array|null
     */
    public function findOnDemandBy(
        $criteria = [],
        ?array $orderBy = null
    ): ?iterable {
        return null;
    }

    /**
     * Find all records. Compatible with the equivalent method in Doctrine.
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param boolean $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return array|null
     */
    public function findAll(?array $orderBy = null, ?string $keyProperty = null, bool $fetchOnDemand = false): ?iterable
    {
        return $this->findBy([], $orderBy, null, null, $keyProperty, $fetchOnDemand);
    }

    /**
     * Insert or update the supplied entity.
     * @param object $entity The entity to insert or update.
     * @param bool $saveChildren Whether or not to also update any child objects. You can set a default value as a 
     * config option (defaults to true).
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int Total number of rows affected (inserts + updates).
     * @throws \Throwable
     */
    public function saveEntity(
        object $entity,
        ?bool $saveChildren = null,
        int &$insertCount = 0,
        int &$updateCount = 0
    ): int {
        $originalClassName = $this->getClassName();
        try {
            $insertCount = 0;
            $updateCount = 0;
            $this->setClassName(ObjectHelper::getObjectClassName($entity));
            $saveChildren = $saveChildren ?? $this->configOptions->saveChildrenByDefault;
            $saveOptions = SaveOptions::create($this->mappingCollection, ['saveChildren' => $saveChildren]);
            $this->beginTransaction();
            $return = $this->objectPersister->saveEntity($entity, $saveOptions, $insertCount, $updateCount);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
        }
        
        return $insertCount + $updateCount;
    }

    /**
     * Insert or update the supplied entities.
     * @param array $entities Array of entities to insert or update.
     * @param bool $updateChildren Whether or not to also insert any new child objects.
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntities(
        array $entities,
        bool $saveChildren = null,
        int &$insertCount = 0,
        int &$updateCount = 0
    ): int {
        try {
            $saveChildren = $saveChildren ?? $this->configOptions->saveChildrenByDefault;
            $saveOptions = SaveOptions::create($this->mappingCollection, ['saveChildren' => $saveChildren]);
            $this->beginTransaction();
            $return = $this->objectPersister->saveEntities($entities, $saveOptions, $insertCount, $updateCount);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        }
    }

    /**
     * Hard delete an entity (and cascade to children, if applicable).
     * @param object $entity The entity to delete.
     * @param boolean $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @param boolean $exceptionIfDisabled Whether or not to barf if deletes are disabled (probably only useful for
     * integration or unit tests)
     * @return int Number of records affected
     * @throws \Exception
     */
    public function deleteEntity(object $entity, $disableCascade = false, $exceptionIfDisabled = true): int
    {
        if (!$this->validateDeletable($exceptionIfDisabled)) {
            return 0;
        }
        
        $originalClassName = $this->getClassName();
        try {
            $this->setClassName(ObjectHelper::getObjectClassName($entity));
            $deleteOptions = DeleteOptions::create($this->mappingCollection, ['disableCascade' => $disableCascade]);
            $this->beginTransaction();
            $return = $this->objectRemover->deleteEntity($entity, $deleteOptions);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
        }
    }

    /**
     * Hard delete multiple entities (and cascade to children, if applicable).
     * @param \Traversable $entities The entities to delete.
     * @param boolean $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @return int Number of records affected
     * @throws \Exception
     */
    public function deleteEntities(
        iterable $entities,
        bool $disableCascade = false,
        bool $exceptionIfDisabled = true
    ): int {
        if (!$this->validateDeletable($exceptionIfDisabled)) {
            return 0;
        }

        $originalClassName = $this->getClassName();
        try {
            $this->setClassName(ObjectHelper::getObjectClassName(reset($entities)));
            $deleteOptions = DeleteOptions::create($this->mappingCollection, ['disableCascade' => $disableCascade]);
            $this->beginTransaction();
            $return = $this->objectRemover->deleteEntities($entities, $deleteOptions);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
        }
    }

    /**
     * Execute a select, insert, update, or delete query directly
     * @param QueryInterface $query
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int|object|array|null Query results, or total number of rows affected.
     * @throws QueryException
     */
    public function executeQuery(QueryInterface $query, int &$insertCount = 0, int &$updateCount = 0): ?int
    {
        if ($query instanceof SelectQueryInterface) {
            return $this->findBy($query);
        } elseif ($query instanceof InsertQueryInterface || $query instanceof UpdateQueryInterface) {
            $saveOptions = SaveOptions::create($this->mappingCollection);
            return $this->objectPersister->executeSave($query, $saveOptions, $insertCount, $updateCount);
        } elseif ($query instanceof DeleteQueryInterface) {
            $deleteOptions = DeleteOptions::create($this->mappingCollection);
            return $this->objectDeleter->deleteBy($deleteQuery, $deleteOptions);
        } else {
            throw new QueryException('Unrecognised query type: ' . ObjectHelper::getObjectClassName($query));
        }
    }

    /**
     * Create an object that does not have to be fully hydrated just to save it as a child of another entity.
     * @param string $className Name of the class.
     * @param array $pkValues Values of the primary key for the instance of the class this reference will represent.
     * @param array $constructorParams If the constructor requires parameters, pass them in here.
     * @return ObjectReferenceInterface|null The resulting object will extend the class name specified, as well as
     * implementing the ObjectReferenceInterface. Returns null if the class does not exist.
     */
    public function getObjectReference(
        string $className,
        array $pkValues = [],
        array $constructorParams = []
    ): ?ObjectReferenceInterface {
        try {
            return $this->proxyFactory->createObjectReferenceProxy($className, $pkValues, $constructorParams);
        } catch (\Exception $ex) {
            $this->throwException($ex);
        }
    }

    /**
     * @return Explanation Information about how the latest result was obtained.
     */
    public function getExplanation(): ?ExplanationInterface
    {
        return null;
    }

    /**
     * Clear entities from memory and require them to be re-loaded afresh from the database.
     * @param string|null $className If supplied, only the cache for the given class will be cleared, otherwise all.
     * @param bool $forgetChangesOnly If true, all entities will be forgotten, otherwise, just changes to the entities
     * will be forgotten.
     * @param bool $propagateToFactory Whether to clear the cache on factories used by lazy loaders. This should
     * normally be true unless the factory itself is callinng this method.
     */
    public function clearCache(
        ?string $className = null,
        bool $forgetChangesOnly = false,
        bool $propagateToFactory = true
    ): void {
        if ($propagateToFactory) { //If the factory is calling us, it will set this to false otherwise we are in a loop.
            $this->repositoryFactory->reset(true, false, $className); //Prevent late bound objects holding on to their entity trackers
        }
        $this->objectFetcher->clearCache($className, $forgetChangesOnly);
    }

    /**
     * Manually begin a transaction (if supported by the storage engine)
     */
    public function beginTransaction(): bool
    {
        return $this->objectPersister->beginTransaction();
    }

    /**
     * Commit a transaction that was started manually (if supported by the storage engine)
     */
    public function commit(): bool
    {
        return $this->objectPersister->commit();
    }

    /**
     * Rollback a transaction that was started manually (if supported by the storage engine)
     */
    public function rollback(): bool
    {
        return $this->objectPersister->rollback();
    }

    /**
     * @param FindOptions $findOptions
     * @param array | SelectQueryInterface $criteria
     * @return mixed
     * @throws ObjectiphyException
     */
    protected function doFindBy(FindOptions $findOptions, $criteria)
    {
        $this->objectFetcher->setFindOptions($findOptions);
        $query = $this->normalizeCriteria($criteria);
        if (!$query->getOrderBy() && $findOptions->orderBy) {
            $orderBy = $this->normalizeOrderBy($findOptions->orderBy);
            if ($orderBy) {
                $query->setOrderBy(...$orderBy);
            }
        }
        if (!$this->getClassName() && $query) {
            $this->setClassName($query->getFrom());
        }
        $this->assertClassNameSet();

        return $this->objectFetcher->executeFind($query);
    }

    protected function normalizeCriteria($criteria, $queryType = SelectQuery::class): QueryInterface
    {
        if (!is_a($queryType, QueryInterface::class, true)) {
            $errorMessage = sprintf('$queryType argument of normalizeCriteria method must be the name of a class that implements %1$s. %2$s does not.', QueryInterface::class, $queryType);
            throw new QueryException($errorMessage);
        }

        if ($criteria instanceof $queryType) {
            $query = $criteria;
        } elseif (is_array($criteria)) {
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties();
            $normalizedCriteria = QB::create()->normalize($criteria, $pkProperties[0] ?? 'id');
            $query = new $queryType();
            $query->setWhere(...$normalizedCriteria);
        } else {
            throw new QueryException('Invalid criteria specified for ' . $queryType);
        }

        return $query;
    }

    /**
     * @param array $orderBy Array of property names, or array keyed on property name with direction as the value.
     * @return array List of FieldExpression objects containing property name and direction.
     */
    protected function normalizeOrderBy(array $orderBy): array
    {
        $normalisedOrderBy = [];
        foreach ($orderBy as $property => $direction) {
            if ($direction instanceof FieldExpression) {
                $normalisedOrderBy[] = $direction;
                continue;
            } elseif (is_int($property) && !in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                $property = $direction; //Indexed array, not associative
                $direction = 'ASC';
            } elseif (!in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                $direction = 'ASC';
            }
            $field = new FieldExpression('`' . $property . '` ' . $direction, false);
            $normalisedOrderBy[] = $field;
        }

        return $normalisedOrderBy;
    }

    /**
     * @throws ObjectiphyException
     */
    protected function assertClassNameSet(): void
    {
        if (!$this->getClassName()) {
            $callingMethod = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'Unknown method';
            $message = sprintf('Please call setClassName before calling %1$s.', $callingMethod);
            throw new ObjectiphyException($message);
        }
    }

    protected function validateDeletable(bool $exceptionIfDisabled = true): bool
    {
        if ($this->configOptions->disableDeleteEntities && $exceptionIfDisabled) {
            //As you are blatantly contradicting yourself, I'mma throw up.
            $errorMessage = 'You have tried to delete an entity, but entity deletes have been disabled. To re-enable '
                . 'deletes, call $repository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, false); first.';
            throw new ObjectiphyException($errorMessage);
        } elseif ($this->configOptions->disableDeleteEntities) {
            return false;
        }

        return true;
    }

    /**
     * One or more config options have changed, so pass along the required values to the various dependencies
     * (it is not recommended to pass the whole config object around and let things help themselves)
     */
    protected function updateConfig(): void
    {
        $this->objectMapper->setConfigOptions(
            $this->configOptions->productionMode,
            $this->configOptions->eagerLoadToOne,
            $this->configOptions->eagerLoadToMany,
            $this->configOptions->guessMappings,
            $this->configOptions->tableNamingStrategy,
            $this->configOptions->columnNamingStrategy
        );
        $this->objectFetcher->setConfigOptions($this->configOptions);
        $this->objectPersister->setConfigOptions(
            $this->configOptions->disableDeleteRelationships,
            $this->configOptions->disableDeleteEntities
        );
    }

    private function throwException(\Throwable $ex): void
    {
        if ($ex instanceof ObjectiphyException) {
            throw $ex;
        } else {
            throw new ObjectiphyException($ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine(), $ex->getCode(), $ex);
        }
    }
}
