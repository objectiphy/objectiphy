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
use Objectiphy\Objectiphy\Contract\QueryInterceptorInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\RepositoryFactoryInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Contract\TransactionInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Factory\ProxyFactory;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Meta\Explanation;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\Pagination;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Query\SelectQuery;
use Objectiphy\Objectiphy\Traits\TransactionTrait;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Main entry point for all ORM operations
 */
class ObjectRepository implements ObjectRepositoryInterface, TransactionInterface
{
    use TransactionTrait;

    protected ConfigOptions $configOptions;
    protected ObjectMapper $objectMapper;
    protected ObjectFetcher $objectFetcher;
    protected ObjectPersister $objectPersister;
    protected ObjectRemover $objectRemover;
    protected ProxyFactory $proxyFactory;
    protected StorageInterface $storage;
    protected ?PaginationInterface $pagination = null;
    protected array $orderBy;
    protected string $className = '';
    protected MappingCollection $mappingCollection;
    protected ExplanationInterface $explanation;
    protected RepositoryFactoryInterface $repositoryFactory;
    protected ?QueryInterceptorInterface $queryInterceptor = null;

    public function __construct(
        ObjectMapper               $objectMapper,
        ObjectFetcher              $objectFetcher,
        ObjectPersister            $objectPersister,
        ObjectRemover              $objectRemover,
        ProxyFactory               $proxyFactory,
        ExplanationInterface       $explanation,
        RepositoryFactoryInterface $repositoryFactory,
        ?ConfigOptions             $configOptions = null
    ) {
        $this->objectMapper = $objectMapper;
        $this->objectFetcher = $objectFetcher;
        $this->storage = $this->objectFetcher->getStorage();
        $this->objectPersister = $objectPersister;
        $this->objectRemover = $objectRemover;
        $this->objectPersister->setObjectRemover($objectRemover);
        $this->objectRemover->setObjectPersister($objectPersister);
        $this->proxyFactory = $proxyFactory;
        $this->explanation = $explanation;
        $this->repositoryFactory = $repositoryFactory;
        if (!$configOptions) {
            $configOptions = new ConfigOptions();
        }
        $this->setConfiguration($configOptions);
        $this->initialise();
    }

    /**
     * This is not part of the public interface and is only here as a dirty hack for 
     * backward compatibility purposes. 
     * @param string $name
     * @param array $arguments
     */
    public function __call(string $name, array $arguments)
    {
        if ($name == 'bcGetMappingCollection') {
            if (!isset($this->mappingCollection)) {
                $this->setClassName($this->getClassName());
            }

            return $this->mappingCollection;
        }
    }

    /**
     * For you filthy animals who want access to the PDO object
     * @return StorageInterface
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
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
     * Reset config options to their default values.
     * @param string $configFile Optionally specify a config file to load the defaults from.
     */
    public function resetConfiguration(string $configFile = '', bool $clearCache = true): void
    {
        $defaultConfig = new ConfigOptions(
            [
                'cacheDirectory' => $this->configOptions->cacheDirectory,
                'devMode' => $this->configOptions->devMode
            ],
            $configFile
        );
        $this->setConfiguration($defaultConfig);
        if ($clearCache) {
            $this->clearCache(null, true);
        }
    }

    /**
     * Set a general configuration option by name. Available options are defined on
     * the Objectiphy\Objectiphy\Config\ConfigOptions class.
     * @param string $optionName
     * @param $value
     * @return mixed The previously set value (or default value if not previously set).
     * @throws ObjectiphyException
     */
    public function setConfigOption(string $optionName, $value)
    {
        $previousValue = $this->configOptions->setConfigOption($optionName, $value);
        $this->updateConfig();
        if (in_array($optionName, [
                ConfigOptions::SERIALIZATION_GROUPS,
                ConfigOptions::HYDRATE_UNGROUPED_PROPERTIES,
                ConfigOptions::MAPPING_DIRECTORY,
            ]) && $value !== $previousValue) {
            //We cannot return a cached entity as the property hydration might be wrong
            $this->clearCache(null, true);
        }
        $this->setClassName($this->getClassName()); //Ensure we have the right mapping collection for the updated config

        return $previousValue;
    }

    /**
     * Set an entity-specific configuration option by name. Available options are
     * defined on the Objectiphy\Objectiphy\Config\ConfigEntity class.
     * @param string $entityClassName
     * @param string $optionName
     * @param $value
     * @throws ObjectiphyException|\ReflectionException
     */
    public function setEntityConfigOption(string $entityClassName, string $optionName, $value): void
    {
        $entityConfigs = $this->configOptions->getConfigOption(ConfigOptions::ENTITY_CONFIG);
        $entityConfig = $entityConfigs[$entityClassName] ?? new ConfigEntity($entityClassName);
        if (is_array($value)) {
            $existingValue = $entityConfig->getConfigOption($optionName);
            $value = array_merge($existingValue, $value);
        }
        $entityConfig->setConfigOption($optionName, $value);
        $entityConfigs[$entityClassName] = $entityConfig;
        $this->setConfigOption(ConfigOptions::ENTITY_CONFIG, $entityConfigs);
        $this->clearCache($entityClassName, true);
    }

    /**
     * Setter for the parent entity class name.
     * @param string $className
     * @throws \ReflectionException
     */
    public function setClassName(string $className, bool $ignoreErrors = false): void
    {
        try {
            if ($className) { //Query might not have one set, in which case, keep the one we've got
                $oldClassName = $this->className;
                $this->className = $className;
                $this->objectMapper->setConfigOptions($this->configOptions); //Ensure we get the correct mappings for this config
                $this->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
                if ($className != $oldClassName) {
                    $this->orderBy = [];
                    //In case of custom repository that does not pass along the find/save options, set defaults here
                    $findOptions = FindOptions::create($this->mappingCollection);
                    $this->objectFetcher->setFindOptions($findOptions);
                    $saveOptions = SaveOptions::create($this->mappingCollection);
                    $this->objectPersister->setSaveOptions($saveOptions);
                    $deleteOptions = DeleteOptions::create($this->mappingCollection);
                    $this->objectRemover->setDeleteOptions($deleteOptions);
                }
            }
        } catch (\Exception $ex) {
            if (!$ignoreErrors) {
                throw $ex;
            }
        }
    }

    /**
     * Getter for the parent entity class name.
     * @return string
     */
    public function getClassName($criteria = []): string
    {
        if ($this->className) {
            return $this->className;
        } elseif (isset($this->mappingCollection)) {
            $this->className = $this->mappingCollection->getEntityClassName();
            return $this->mappingCollection->getEntityClassName();
        } elseif ($criteria && $criteria instanceof QueryInterface) {
            $this->className = $criteria->getClassName();
            return $this->className;
        }

        return '';
    }

    /**
     * Set a pagination object (to store and supply information about how the results are paginated)
     * (can be set to null to remove previously set pagination)
     * @param PaginationInterface|null $pagination
     */
    public function setPagination(?PaginationInterface $pagination): void
    {
        $this->pagination = $pagination;
    }

    public function getPagination(): ?PaginationInterface
    {
        return $this->pagination;
    }

    /**
     * @param array $orderBy Key = property name, value = ASC or DESC.
     */
    public function setOrderBy(array $orderBy): void
    {
        $this->orderBy = $orderBy;
    }

    /**
     * @deprecated
     * You can use this to modify low-level queries immediately before execution, but only if
     * the storage mechanism allows it (it is not part of the interface, so storage classes
     * are not obliged to support this method - the default one does, for backward compatibility
     * purposes only).
     * Don't use this unless you absolutely have no other choice!
     * @param QueryInterceptorInterface $queryInterceptor
     */
    public function setInterceptor(?QueryInterceptorInterface $queryInterceptor = null)
    {
        $this->queryInterceptor = $queryInterceptor;
        if (method_exists($this->getStorage(), 'setInterceptor')) {
            $this->getStorage()->setInterceptor($queryInterceptor);
            if (method_exists($this->getExplanation(), 'setInterceptor')) {
                $this->getExplanation()->setInterceptor($queryInterceptor);
            }
        }
    }

    /**
     * Find a single record (and hydrate it as an entity) with the given primary key value. Compatible with the
     * equivalent method in Doctrine.
     * @param mixed $id Primary key value - for composite keys, can be an array
     * @return object|array|null
     * @throws ObjectiphyException|\Throwable
     */
    public function find($id)
    {
        $this->beforeQuery(__METHOD__);
        if ($id instanceof QueryInterface) {
            $this->afterQuery(__METHOD__);
            throw new QueryException('The find method should only be used with a primary key value. To execute a query, use findBy instead.');
        } elseif (is_object($id)) {
            //Try to find primary key value, otherwise throw up
            $id = $this->mappingCollection->getPrimaryKeyValues($id);
            if (!$id) {
                $this->afterQuery(__METHOD__);
                throw new QueryException('The find method should only be used with a primary key value. Cannot resolve the given object to a value.');
            }
        }

        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet();
        $existingEntity = $this->objectFetcher->getExistingEntity($this->getClassName(), $id);
        if ($existingEntity) {
            $this->afterQuery(__METHOD__);
            return $existingEntity;
        }
        $pkProperties = $this->mappingCollection->getPrimaryKeyProperties();
        if (!$pkProperties) {
            $errorMessage = sprintf(
                'The current entity (`%1$s`) does not have a primary key, so you cannot use the find method. Either specify a primary key in the mapping information, or use findOneBy instead.',
                $this->getClassName()
            );
            $this->afterQuery(__METHOD__);
            $this->throwException(new ObjectiphyException($errorMessage));
        }

        $idArray = is_array($id) ? $id : [$id];
        if (count($idArray) != count($pkProperties)) {
            $this->afterQuery(__METHOD__);
            throw new QueryException('Number of primary key properties does not match number of values given.');
        }

        $result = $this->findOneBy(array_combine($pkProperties, $idArray));
        $this->afterQuery(__METHOD__);

        return $result;
    }

    /**
     * Find a single record (and hydrate it as an entity) for the given criteria. Compatible with the equivalent method
     * in Doctrine.
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @return object|array|null
     * @throws ObjectiphyException|QueryException|\ReflectionException|\Throwable
     */
    public function findOneBy($criteria = [])
    {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet($criteria);
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => false,
            'bindToEntities' => $this->configOptions->bindToEntities,
            'allowDuplicates' => $this->configOptions->allowDuplicates
        ]);

        $result = $this->doFindBy($findOptions, $criteria);
        $this->afterQuery(__METHOD__);

        return $result;
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
     * @param string|null $indexBy If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param bool $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set (for streaming large amounts of data).
     * @return array|object|null
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function findBy(
        $criteria = [],
        ?array $orderBy = null,
        $limit = null,
        $offset = null,
        ?string $indexBy = null,
        bool $fetchOnDemand = false
    ): ?iterable {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet($criteria);
        $eagerLoadToOneSetting = $this->getConfiguration()->eagerLoadToOne;
        if ($fetchOnDemand) { //Try to eager load to avoid nested queries
            $this->setConfigOption(ConfigOptions::EAGER_LOAD_TO_ONE, true);
        }
        $this->setOrderBy(array_filter($orderBy ?? $this->orderBy ?? []));
        if ($limit) { //Only for Doctrine compatibility
            $this->pagination = new Pagination($limit, round($offset / $limit) + 1);
        }
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => true,
            'orderBy' => $this->orderBy,
            'indexBy' => $indexBy ?? '',
            'onDemand' => $fetchOnDemand,
            'pagination' => $this->pagination ?? null,
            'bindToEntities' => $this->configOptions->bindToEntities,
            'allowDuplicates' => $this->configOptions->allowDuplicates
        ]);

        $result = $this->doFindBy($findOptions, $criteria);
        $this->setConfigOption(ConfigOptions::EAGER_LOAD_TO_ONE, $eagerLoadToOneSetting);
        if ($orderBy) { //Don't hang onto it if it was just passed in for this query
            $this->setOrderBy([]);
        }
        $this->afterQuery(__METHOD__);

        return $result;
    }

    /**
     * Alias for findBy but automatically sets the $fetchOnDemand flag to true and avoids needing to supply null values
     * for the arguments that are not applicable (findBy thus remains compatible with Doctrine).
     * @param array|SelectQueryInterface $criteria An array of criteria or a Query object built by the QueryBuilder. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param array|null $orderBy
     * @return array|null
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function findOnDemandBy(
        $criteria = [],
        ?array $orderBy = null
    ): ?iterable {
        return $this->findBy($criteria, $orderBy, null, null, null, true);
    }

    public function findOneValueBy($criteria = [], string $valueProperty = '')
    {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet($criteria);
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => false,
            'bindToEntities' => false,
            'scalarProperty' => $valueProperty,
            'allowDuplicates' => $this->configOptions->allowDuplicates
        ]);

        $result = $this->doFindBy($findOptions, $criteria);
        if (is_array($result)) {
            $result = reset($result);
        }
        $this->afterQuery(__METHOD__);

        return $result;
    }

    /**
     * Find the value of a single property on multiple entities
     * @param array $criteria
     * @param string $valueProperty
     * @param array|null $orderBy
     * @param string|null $indexBy
     * @param bool $fetchOnDemand
     * @return mixed
     */
    public function findValuesBy(
        $criteria = [],
        string $valueProperty = '',
        ?array $orderBy = null,
        ?string $indexBy = null,
        bool $fetchOnDemand = false
    ) {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet($criteria);
        $this->setOrderBy(array_filter($orderBy ?? $this->orderBy ?? []));
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => true,
            'orderBy' => $this->orderBy,
            'indexBy' => $indexBy ?? '',
            'onDemand' => $fetchOnDemand,
            'pagination' => $this->pagination ?? null,
            'bindToEntities' => false,
            'scalarProperty' => $valueProperty,
            'allowDuplicates' => $this->configOptions->allowDuplicates
        ]);
        $result = $this->doFindBy($findOptions, $criteria);
        if (is_iterable($result)) {
            if (is_array($result) && is_array(reset($result))) {
                $key = array_key_first(reset($result));
                $this->afterQuery(__METHOD__);
                return array_column($result, $key);
            } else {
                $this->afterQuery(__METHOD__);
                return $result;
            }
        }
        $this->afterQuery(__METHOD__);

        return [];
    }

    /**
     * Find the value of a single property on multiple entities and return as an on-demand iterable result
     * @param array $criteria
     * @param string $valueProperty
     * @param array|null $orderBy
     * @return IterableResult
     */
    public function findOnDemandValuesBy(
        $criteria = [],
        string $valueProperty = '',
        ?array $orderBy = null
    ): IterableResult {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet($criteria);
        $this->setOrderBy(array_filter($orderBy ?? $this->orderBy ?? []));
        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => true,
            'orderBy' => $this->orderBy,
            'onDemand' => true,
            'pagination' => $this->pagination ?? null,
            'bindToEntities' => false,
            'scalarProperty' => $valueProperty,
            'allowDuplicates' => $this->configOptions->allowDuplicates
        ]);
        $result = $this->doFindBy($findOptions, $criteria);
        $this->afterQuery(__METHOD__);

        return $result;
    }

    /**
     * Find all records. Compatible with the equivalent method in Doctrine.
     * @param array|null $orderBy
     * @param string|null $indexBy If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param bool $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return iterable|null
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function findAll(?array $orderBy = null, ?string $indexBy = null, bool $fetchOnDemand = false): ?iterable
    {
        return $this->findBy([], $orderBy, null, null, $indexBy, $fetchOnDemand);
    }
    
    /**
     * Count records that match criteria
     * @param array $criteria
     * @return int
     */
    public function count($criteria = []): int
    {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $this->assertClassNameSet($criteria);
        $count = intval($this->findOneValueBy($criteria, 'COUNT(*)'));
        $this->afterQuery(__METHOD__);

        return $count;
    }

    /**
     * Insert or update the supplied entity.
     * @param object $entity The entity to insert or update.
     * @param bool $saveChildren Whether or not to also update any child objects. You can set a default value as a
     * config option (defaults to true).
     * @param bool $replace Whether or not to attempt to insert, and if the record already exists, update it (for
     * cases where you are generating a primary key value yourself)
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @param int $deleteCount Number of rows deleted.
     * @return int Total number of rows affected (inserts + updates).
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function saveEntity(
        object $entity,
        ?bool $saveChildren = null,
        ?bool $replace = false,
        int &$insertCount = 0,
        int &$updateCount = 0,
        int &$deleteCount = 0
    ): int {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $originalClassName = $this->getClassName();
        try {
            $insertCount = 0;
            $updateCount = 0;
            $this->setClassName(ObjectHelper::getObjectClassName($entity));
            $saveChildren = $saveChildren ?? $this->configOptions->saveChildrenByDefault;
            $saveOptions = SaveOptions::create($this->mappingCollection, [
                'saveChildren' => $saveChildren,
                'replaceExisting' => boolval($replace),
                'parseDelimiters' => false,
            ]);
            $this->beginTransaction();
            $return = $this->objectPersister->saveEntity($entity, $saveOptions, $insertCount, $updateCount, $deleteCount, true);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
            $this->afterQuery(__METHOD__);
        }
        
        return $insertCount + $updateCount;
    }

    /**
     * Insert or update the supplied entities.
     * @param array $entities Array of entities to insert or update.
     * @param bool|null $saveChildren
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @param int $deleteCount
     * @return int Number of rows affected.
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function saveEntities(
        iterable $entities,
        bool $saveChildren = null,
        ?bool $replace = false,
        int &$insertCount = 0,
        int &$updateCount = 0,
        int &$deleteCount = 0
    ): int {
        $this->beforeQuery(__METHOD__);
        if (!$entities) {
            return 0;
        }
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $originalClassName = $this->getClassName();
        try {
            $saveChildren = $saveChildren ?? $this->configOptions->saveChildrenByDefault;
            $this->setClassName(ObjectHelper::getObjectClassName(reset($entities)));
            $saveOptions = SaveOptions::create($this->mappingCollection, [
                'saveChildren' => $saveChildren,
                'replaceExisting' => $replace,
                'parseDelimiters' => false
            ]);
            $this->beginTransaction();
            $return = $this->objectPersister->saveEntities(
                $entities,
                $saveOptions,
                $insertCount,
                $updateCount,
                $deleteCount
            );
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
            $this->afterQuery(__METHOD__);
        }
    }

    public function getLastInsertId(): ?int
    {
        return $this->objectPersister->getLastInsertId();
    }
    
    /**
     * Hard delete an entity (and cascade to children, if applicable).
     * @param object $entity The entity to delete.
     * @param bool $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @param bool $exceptionIfDisabled Whether or not to barf if deletes are disabled (probably only useful for
     * integration or unit tests).
     * @param int $updateCount Number of records updated (where child records lose their parents but do not get
     * deleted themsselves, they may be updated with null values for the foreign key).
     * @return int Number of records deleted.
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function deleteEntity(object $entity, $disableCascade = false, $exceptionIfDisabled = true, int &$updateCount = 0): int
    {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        if (!$this->validateDeletable($exceptionIfDisabled)) {
            return 0;
        }
        
        $originalClassName = $this->getClassName();
        try {
            $this->setClassName(ObjectHelper::getObjectClassName($entity));
            $deleteOptions = DeleteOptions::create($this->mappingCollection, ['disableCascade' => $disableCascade]);
            $this->beginTransaction();
            $return = $this->objectRemover->deleteEntity($entity, $deleteOptions, $updateCount);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
            $this->afterQuery(__METHOD__);
        }
    }

    /**
     * Hard delete multiple entities (and cascade to children, if applicable).
     * @param \Traversable $entities The entities to delete.
     * @param bool $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @param bool $exceptionIfDisabled Whether or not to throw an exception if an attempt is made to delete when
     * deletes are disabled (if false, it will just silently return zero).
     * @param int $updateCount Number of records updated (where child records lose their parents but do not get
     * deleted themselves, they may be updated with null values for the foreign key).
     * @return int Number of records deleted.
     * @throws ObjectiphyException|\ReflectionException|\Throwable
     */
    public function deleteEntities(
        iterable $entities,
        bool $disableCascade = false,
        bool $exceptionIfDisabled = true,
        int $updateCount = 0
    ): int {
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        if (!$this->validateDeletable($exceptionIfDisabled) || !$entities) {
            return 0;
        }

        $originalClassName = $this->getClassName();
        try {
            $entityArray = is_array($entities) ? $entities : iterator_to_array($entities);
            $this->setClassName(ObjectHelper::getObjectClassName(reset($entityArray)));
            $deleteOptions = DeleteOptions::create($this->mappingCollection, ['disableCascade' => $disableCascade]);
            $this->beginTransaction();
            $return = $this->objectRemover->deleteEntities($entities, $deleteOptions, $updateCount);
            $this->commit();

            return $return;
        } catch (\Throwable $ex) {
            $this->rollback();
            $this->throwException($ex);
        } finally {
            $this->setClassName($originalClassName);
            $this->afterQuery(__METHOD__);
        }
    }

    /**
     * Execute a select, insert, update, or delete query directly
     * @param QueryInterface $query
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @param int $deleteCount Number of rows deleted.
     * @param int $lastInsertId Auto-incremented id of inserted record, if applicable.
     * @return int|object|array|null Query results, or total number of rows affected.
     * @throws ObjectiphyException|QueryException|\ReflectionException|\Throwable
     */
    public function executeQuery(
        QueryInterface $query,
        int &$insertCount = 0,
        int &$updateCount = 0,
        int &$deleteCount = 0,
        ?int &$lastInsertId = null
    ) {
        //TODO: Use command bus pattern to send queries of different types to different handlers
        $this->beforeQuery(__METHOD__);
        $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
        $originalClassName = $this->getClassName();
        $query->setClassName($query->getClassName() ?: $originalClassName);
        $this->setClassName($query->getClassName(), strpos($query->getClassName(), '`') !== false); //Ignore errors in case of explicit table name
        $this->mappingCollection->setGroups(...$this->configOptions->serializationGroups);
        if ($query instanceof SelectQueryInterface) {
            $this->getConfiguration()->disableEntityCache ? $this->clearCache() : false;
            $this->assertClassNameSet();
            $findOptions = $this->objectFetcher->inferFindOptionsFromQuery($query, $this->mappingCollection);
            $result = $this->doFindBy($findOptions, $query);
        } elseif ($query instanceof InsertQueryInterface || $query instanceof UpdateQueryInterface) {
            $saveOptions = SaveOptions::create($this->mappingCollection);
            $result = $this->objectPersister->executeSave($query, $saveOptions, $insertCount, $updateCount, $lastInsertId);
        } elseif ($query instanceof DeleteQueryInterface) {
            $deleteOptions = DeleteOptions::create($this->mappingCollection);
            $deleteCount = $this->objectRemover->executeDelete($query, $deleteOptions);
            $result = $deleteCount;
        } else {
            throw new QueryException('Unrecognised query type: ' . ObjectHelper::getObjectClassName($query));
        }
        $this->setClassName($originalClassName);
        $this->afterQuery(__METHOD__);

        return $result;
    }
    
    /**
     * Create an object that does not have to be fully hydrated just to save it as a child of another entity.
     * @param string $className Name of the class.
     * @param array $pkValues Values of the primary key for the instance of the class this reference will represent.
     * @param array $constructorParams If the constructor requires parameters, pass them in here.
     * @return ObjectReferenceInterface|null The resulting object will extend the class name specified, as well as
     * implementing the ObjectReferenceInterface. Returns null if the class does not exist.
     * @throws ObjectiphyException|\Throwable
     */
    public function getObjectReference(
        string $className,
        array $pkValues = [],
        array $constructorParams = []
    ): ?ObjectReferenceInterface {
        try {
            if (array_key_first($pkValues) === 0) { //Key property name(s) not specified - look them up
                if (!isset($this->mappingCollection)) {
                    $this->setClassName($className, true);
                } else {
                    $currentClassName = $this->mappingCollection->getEntityClassName();
                    if ($currentClassName != $className) {
                        $this->setClassName($className, true);
                    }
                }
                $pkProperties = $this->mappingCollection->getPrimaryKeyProperties();
                if (($currentClassName ?? false) && $currentClassName != $className) {
                    $this->setClassName($currentClassName);
                }
                if (!$pkProperties && count($pkValues == 1)) {
                    $idValue = reset($pkValues);
                    $pkValues = ['id' => $idValue]; //Not a mapped entity, so just assume id and hope for the best
                } else {
                    $pkValues = array_combine($pkProperties, $pkValues);
                }
            }

            return $this->proxyFactory->createObjectReferenceProxy($className, $pkValues, $constructorParams);
        } catch (\Throwable $ex) {
            $this->throwException($ex);
        }
    }

    /**
     * @return ExplanationInterface Information about how the latest result was obtained.
     */
    public function getExplanation(): ?ExplanationInterface
    {
        return $this->explanation;
    }

    /**
     * @param bool $parameterise Whether or not to replace parameters with their actual values.
     * @return string Convenience method to get the last SQL query generated.
     */
    public function getSql(bool $parameterise = true): string
    {
        return $this->explanation->getSql($parameterise);
    }

    /**
     * Clear entities from memory and require them to be re-loaded afresh from the database.
     * @param string|null $className If supplied, only the cache for the given class will be cleared, otherwise all.
     * @param bool $clearMappings Whether or not to also clear mapping information.
     * @param bool $thisRepoOnly For internal use only, to prevent recursion.
     */
    public function clearCache(?string $className = null, bool $clearMappings = false, bool $thisRepoOnly = false): void
    {
        if (!$thisRepoOnly) {
            $this->repositoryFactory->clearCache($className, $clearMappings);
        }
        if ($clearMappings) {
            $this->clearLocalMappingCache();
        }
    }

    public function clearLocalMappingCache(?string $className = null)
    {
        $this->objectMapper->clearMappingCache($className);
        if (!$className || $this->mappingCollection->usesClass($className)) {
            unset($this->mappingCollection);
        }
    }

    /**
     * Delete all collected data (to free up memory for large and/or multiple queries).
     */
    public function clearQueryHistory(): void
    {
        $this->explanation->clear();
    }

    /**
     * If any values are already known, they can be specified here to avoid having to look them up when reading. This
     * is mainly used to allow a late bound child to know about its parent, and helps to avoid recursion when eager
     * loading.
     * @param array $knownValues The known values, keyed on property name.
     * @throws ObjectiphyException|\ReflectionException
     */
    public function setKnownValues(array $knownValues)
    {
        if (empty($this->mappingCollection)) {
            $this->mappingCollection = $this->objectMapper->getMappingCollectionForClass($this->className);
        }
        if ($this->mappingCollection) {
            foreach ($knownValues as $property => $value) {
                $propertyMapping = $this->mappingCollection->getPropertyMapping($property);
                if ($propertyMapping) {
                    $propertyMapping->isFetchable = false;
                }
            }
        }
        $this->objectFetcher->setKnownValues($knownValues);
    }

    /**
     * Set all primary keys to null so you can save a copy as a new record with
     * all of its children (note, this will not set keys to null if there is an
     * autoIncrement mapping definition set to false). This method does NOT 
     * clone the object, it UPDATES it to remove (set to null, or unset) all 
     * primary key property values (ie. on the parent and all child objects). 
     * You can then save it back to the database to create a copy with new 
     * autoincrement IDs.
     */
    public function clearAllPrimaryKeyValues(object $entity, array $excludeProperties = [], $maxDepth = 5): void
    {
        $closure = function($object) {
            $objectClassName = ObjectHelper::getObjectClassName($object);
            $success = ObjectHelper::clearPrimaryKey($object, $this->mappingCollection);
            $this->clearCache(ObjectHelper::getObjectClassName($object)); //We won't be able to save objects that are still tracked
            return $success; 
        }; 
        $this->traverseHierarchy($entity, $closure, $excludeProperties, $maxDepth);
    }

    /**
     * Convenience method to allow you to perform some action on each object in the hierarchy.
     * If the closure returns false, the traversal will stop (allows you to stop going deeper
     * than is necessary, although infinite recursion will be prevented automatically anyway,
     * and you can also specify a maximum depth)
     */
    public function traverseHierarchy(object $entity, \closure $closure, array $excludeProperties = [], int $maxDepth = 5): void
    {
        ObjectHelper::traverseHierarchy($entity, $this->objectMapper, $closure, $excludeProperties, $maxDepth);
    }

    /**
     * Converts an array of criteria into a query if applicable. You do not normally need to call this directly as it
     * gets called automatically whenever you call a find method. It is only public for backward compatibility purposes.
     * @param $criteria
     * @param string $queryType
     * @return QueryInterface
     * @throws QueryException
     */
    public function normalizeCriteria($criteria, $queryType = SelectQuery::class): QueryInterface
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
            $this->validateCriteria($normalizedCriteria);
            $query = new $queryType();
            $query->setWhere(...$normalizedCriteria);
        } else {
            $message = sprintf('Invalid criteria specified for %1$s.', $queryType);
            if ($criteria instanceof QueryBuilder) {
                $message .= ' You have passed in an instance of QueryBuilder instead of an actual Query. Please call the appropriate build method (eg. buildSelectQuery), and pass in the resulting query (note, if you want to build the query and assign it to a variable, make sure you do actually assign it - just calling the method will not work if you don\'t use the response!)';
            }
            throw new QueryException($message);
        }

        return $query;
    }

    /**
     * @param FindOptions $findOptions
     * @param array | SelectQueryInterface $criteria
     * @return mixed
     * @throws ObjectiphyException
     * @throws QueryException|\ReflectionException|\Throwable
     */
    protected function doFindBy(FindOptions $findOptions, $criteria)
    {
        if (empty($findOptions->orderBy) && $this->orderBy) {
            $findOptions->orderBy = $this->orderBy;
        }
        $this->objectMapper->setConfigOptions($this->configOptions);
        $this->objectFetcher->setFindOptions($findOptions);
        $query = $this->normalizeCriteria($criteria);
        if (!$query->getOrderBy() && $findOptions->orderBy) {
            $tempQuery = QB::create()->orderBy($findOptions->orderBy)->buildSelectQuery();
            $query->setOrderBy(...$tempQuery->getOrderBy());
            $query->setOrderByDirections(...$tempQuery->getOrderByDirections());
        }
        if (!$this->getClassName() && $query) {
            $this->setClassName($query->getClassName());
        }
        $this->assertClassNameSet();
        if (isset($this->queryInterceptor)) {
            $this->queryInterceptor->setCriteria($query);
        }
        $value = $this->objectFetcher->executeFind($query);

        return $value;
    }

    /**
     * If associative array, key MUST relate to a property path - otherwise query will try to join everything
     * and criteria will likely find nothing (for what is most likely just a typo). If you really want to
     * specify a query with no property, use an actual query, not an associative array.
     * @param array $normalizedCriteria
     * @throws QueryException
     */
    protected function validateCriteria(array $normalizedCriteria): void
    {
        $propertyFound = false;
        foreach ($normalizedCriteria as $criteriaExpression) {
            if ($criteriaExpression instanceof CriteriaExpression) {
                $propertyPath = $criteriaExpression->property->getPropertyPath();
                if ($propertyPath && $this->mappingCollection->getPropertyMapping($propertyPath)) {
                    $propertyFound = true;
                    break;
                } elseif ($propertyPath && strpos($propertyPath, '.') !== false) {
                    //If lazy loading, we might not know about child properties
                    $firstPart = strtok($propertyPath, '.');
                    $propertyMapping = $this->mappingCollection->getPropertyMapping($firstPart);
                    $propertyFound = $propertyMapping && $propertyMapping->isLateBound();
                }
            }
        }

        if ($normalizedCriteria && !$propertyFound) {
            $message = sprintf('Criteria specified does not relate to a known property on %1$s. If you are using an array to specify criteria, use the format [$property => $value] or [$property => [\'operator\' => $operator, \'value\' => $value]. Otherwise please specify a valid property path, or use the Query Builder to create a custom query.', $this->getClassName());
            throw new QueryException($message);
        }
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
            $field = new FieldExpression('%' . $property . '% ' . $direction, false);
            $normalisedOrderBy[] = $field;
        }

        return $normalisedOrderBy;
    }

    /**
     * @throws ObjectiphyException|\ReflectionException
     */
    protected function assertClassNameSet($criteria = []): void
    {
        $className = $this->getClassName($criteria);
        if (!$className) {
            $callingMethod = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'Unknown method';
            $message = sprintf('Please call setClassName before calling %1$s.', $callingMethod);
            throw new ObjectiphyException($message);
        } elseif (!isset($this->mappingCollection) || $this->mappingCollection->getEntityClassName() != $className) {
            $this->setClassName($className);
        }
    }

    /**
     * @param bool $exceptionIfDisabled
     * @return bool
     * @throws ObjectiphyException
     */
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
        $this->objectMapper->setConfigOptions($this->configOptions);
        $this->objectFetcher->setConfigOptions($this->configOptions);
        $this->objectPersister->setConfigOptions($this->configOptions);
        $this->objectRemover->setConfigOptions($this->configOptions);
    }

    protected function initialise(): void
    {
        // This is just here so that custom repos can override it to carry out
        // any initialisation they want without having to worry about having a
        // massive constructor.
    }

    protected function beforeQuery(string $methodName): void
    {
        // This is here so that custom repos can override it to carry out any
        // processing necessary immediately before any queries are run (eg.
        // setting config options for an entity that is also used by other
        // repositories)
    }

    protected function afterQuery(string $methodName): void
    {
        // This is here so that custom repos can override it to carry out any
        // processing necessary immediately after any queries are run (eg.
        // clearing config options for an entity that is also used by other
        // repositories)
    }

    /**
     * @param \Throwable $ex
     * @throws ObjectiphyException|\Throwable
     */
    private function throwException(\Throwable $ex): void
    {
        if ($ex instanceof ObjectiphyException) {
            throw $ex;
        } else {
            throw new ObjectiphyException($ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine(), $ex->getCode(), $ex);
        }
    }
}
