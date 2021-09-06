<?php

/**
 * RSW: TODO: Make this better. Possibly create objects in a generic way or split into smaller factories.
 */
declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Annotations\CachedAnnotationReader;
use Objectiphy\Objectiphy\Cache\FileCache;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\ObjectRepositoryInterface;
use Objectiphy\Objectiphy\Contract\RepositoryFactoryInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Database\MySql\DataTypeHandlerMySql;
use Objectiphy\Objectiphy\Database\MySql\JoinProviderMySql;
use Objectiphy\Objectiphy\Database\MySql\SqlDeleterMySql;
use Objectiphy\Objectiphy\Database\MySql\WhereProviderMySql;
use Objectiphy\Objectiphy\Database\PdoStorage;
use Objectiphy\Objectiphy\Database\MySql\SqlSelectorMySql;
use Objectiphy\Objectiphy\Database\MySql\SqlUpdaterMySql;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\MappingProvider\MappingProvider;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderAnnotation;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderDoctrineAnnotation;
use Objectiphy\Objectiphy\Meta\Explanation;
use Objectiphy\Objectiphy\NamingStrategy\NameResolver;
use Objectiphy\Objectiphy\Orm\EntityTracker;
use Objectiphy\Objectiphy\Orm\ObjectRepository;
use Objectiphy\Objectiphy\Orm\ObjectMapper;
use Objectiphy\Objectiphy\Orm\ObjectBinder;
use Objectiphy\Objectiphy\Orm\ObjectFetcher;
use Objectiphy\Objectiphy\Orm\ObjectPersister;
use Objectiphy\Objectiphy\Orm\ObjectRemover;
use Objectiphy\Objectiphy\Orm\ObjectUnbinder;
use Objectiphy\Objectiphy\Query\InternalQueryHelper;
//Conditional import - we won't force you to have Psr\SimpleCache installed
use Objectiphy\Annotations\PsrSimpleCacheInterface;
use Psr\SimpleCache\CacheInterface;

if (!interface_exists('\Psr\SimpleCache\CacheInterface')) {
    class_alias(PsrSimpleCacheInterface::class, '\Psr\SimpleCache\CacheInterface');
}

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class RepositoryFactory implements RepositoryFactoryInterface
{
    private \PDO $pdo;
    private CacheInterface $cache;
    private ConfigOptions $configOptions;
    private MappingProviderInterface $mappingProvider;
    private SqlSelectorInterface $sqlSelector;
    private SqlUpdaterInterface $sqlUpdater;
    private SqlDeleterInterface  $sqlDeleter;
    private DataTypeHandlerInterface $dataTypeHandler;
    private ObjectMapper $objectMapper;
    private StorageInterface $storage;
    private ProxyFactory $proxyFactory;
    private EntityTracker $entityTracker;
    private InternalQueryHelper $queryHelper;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;
    private SqlStringReplacer $stringReplacer;
    private ObjectRemover $objectRemover;
    private ?ExplanationInterface $explanation = null;
    /**
     * @var ObjectRepositoryInterface[]
     */
    private array $repositories = [];
    private CollectionFactoryInterface $collectionFactory;
    private $useCacheForMappingProvider = false; //Default file cache just slows things down, but an in-memory cache might speed things up

    /**
     * @param \PDO $pdo A PDO database connection
     * @param string $cacheDirectory Where to store proxy class definition files and cache files
     * @param bool $devMode Whether to rebuild the proxies on each call or not (performance penalty)
     * @throws ObjectiphyException
     */
    public function __construct(
        \PDO $pdo, 
        string $cacheDirectory = '', 
        bool $devMode = true, 
        string $configIniFile = ''
    ) {
        $this->pdo = $pdo;
        if ($configIniFile !== null) {
            $configIniFile = file_exists($configIniFile) ? $configIniFile : '';
        }
        $configOptions = new ConfigOptions([
            'cacheDirectory' => $cacheDirectory,
            'devMode' => $devMode,
        ], $configIniFile);
        $this->configOptions = $configOptions;
        if ($devMode) {
            //Delete all proxy classes and clear the mapping cache
            $proxyFactory = $this->createProxyFactory();
            $proxyFactory->clearProxyCache();
            $this->getObjectMapper()->clearMappingCache();
        }
    }

    /**
     * @param array $configOptions Keyed by option name
     * @throws ObjectiphyException
     */
    public function setConfigOptions(array $configOptions): void
    {
        foreach ($configOptions as $key => $value) {
            $this->configOptions->setConfigOption($key, $value);
        }
    }

    public function reset(): void
    {
        unset($this->mappingProvider);
        unset($this->sqlSelector);
        unset($this->sqlUpdater);
        unset($this->sqlDeleter);
        unset($this->dataTypeHandler);
        unset($this->objectMapper);
        unset($this->storage);
        unset($this->proxyFactory);
        unset($this->joinProvider);
        unset($this->whereProvider);
        unset($this->objectRemover);
    }

    /************************************/
    /* Allow for custom implementations */
    /************************************/

    public function setCollectionFactoryClass(CollectionFactoryInterface $collectionFactory): void
    {
        $this->collectionFactoryClass = $factoryClassName;
    }

    public function setSqlBuilder(SqlSelectorInterface $sqlSelector, DataTypeHandlerInterface $dataTypeHandler): void
    {
        $this->sqlSelector = $sqlSelector;
        $this->dataTypeHandler = $dataTypeHandler;
    }

    /**
     * @param MappingProviderInterface $mappingProvider
     * @param bool $useCache Whether or not to cache mapping definitions (this can slow things down, depending on
     * the caching mechanism used - eg. a file-based cache which has to serialize and write to file will be slower
     * than an in-memory cache and might be slower than not caching at all).
     */
    public function setMappingProvider(MappingProviderInterface $mappingProvider, bool $useCache = true): void
    {
        $this->mappingProvider = $mappingProvider;
        $this->useCacheForMappingProvider = $useCache;
    }

    public function setDataTypeHandler(DataTypeHandlerInterface $dataTypeHandler): void
    {
        $this->dataTypeHandler = $dataTypeHandler;
    }

    public function setStorage(StorageInterface $storage): void
    {
        $this->storage = $storage;
    }
    
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /************************************/
    /* End of custom implementations    */
    /************************************/

    public function getCache(): CacheInterface
    {
        if (!isset($this->cache)) {
            $this->cache = $this->createCache();
        }
        
        return $this->cache;
    }

    /**
     * If no custom mapping provider has been set, return the default one, which reads both Doctrine and Objectiphy
     * annotations.
     * @return MappingProviderInterface
     */
    public function getMappingProvider(): MappingProviderInterface
    {
        if (!isset($this->mappingProvider)) {
            $annotationReader = $this->getAnnotationReader();
            $baseMappingProvider = new MappingProvider();
            $doctrineMappingProvider = new MappingProviderDoctrineAnnotation($baseMappingProvider, $annotationReader);
            $this->mappingProvider = new MappingProviderAnnotation($doctrineMappingProvider, $annotationReader);
            $this->mappingProvider->setThrowExceptions($this->configOptions->devMode);
        }

        return $this->mappingProvider;
    }

    public function getAnnotationReader()
    {
        //Decorate a mapping provider for Doctrine/Objectiphy annotations
        $annotationReader = new AnnotationReader();
        $annotationReader->setClassNameAttributes(
            [
                'childClassName',
                'targetEntity',
                'collectionClass',
                'repositoryClassName'
            ]
        );

        if ($this->useCacheForMappingProvider) { //FileCache is too slow for this
            $cache = $this->getCache();
            $cachedAnnotationReader = new CachedAnnotationReader($annotationReader, $cache);
        } else {
            $cachedAnnotationReader = $annotationReader; //Not actually cached
        }

        return $cachedAnnotationReader;
    }

    public function setExplanation(ExplanationInterface $explanation)
    {
        $this->explanation = $explanation;
    }

    /**
     * @param string $entityClassName
     * @param string|null $repositoryClassName
     * @param ConfigOptions|null $configOptions
     * @param bool $resetFirst
     * @return ObjectRepositoryInterface
     * @throws ObjectiphyException|\ReflectionException
     */
    public function createRepository(
        string $entityClassName = '',
        string $repositoryClassName = null,
        ?ConfigOptions $configOptions = null,
        bool $resetFirst = false
    ): ObjectRepositoryInterface {
        if ($resetFirst) {
            $this->reset(); //When late binding, we will need new instances to prevent cross-pollination
        }
        $configOptions ??= $this->configOptions;
        $configHash = $configOptions->getHash($repositoryClassName ?: '');
        if (!isset($this->repositories[$entityClassName][$configHash])) {
            $repositoryClassName = $this->getRepositoryClassName($repositoryClassName, $entityClassName);
            /** @var ObjectRepository $objectRepository */
            $objectRepository = new $repositoryClassName(
                $this->getObjectMapper(),
                $this->createObjectFetcher($configOptions),
                $this->createObjectPersister(),
                $this->getObjectRemover(),
                $this->getProxyFactory($configOptions),
                $this->getExplanation(),
                $this,
                $configOptions
            );
            if ($entityClassName) {
                $objectRepository->setClassName($entityClassName);
            }
            $this->repositories[$entityClassName][$configHash] = $objectRepository;
        }

        return $this->repositories[$entityClassName][$configHash];
    }

    public function clearCache(?string $className = null, bool $clearMappingCache = false): void
    {
        $this->entityTracker->clear($className);
        if ($clearMappingCache) {
            if (isset($this->objectMapper)) {
                $this->objectMapper->clearMappingCache($className);
            }
            foreach ($this->repositories ?? [] as $key => $repositoryList) {
                foreach ($repositoryList ?? [] as $repository) {
                    $repository->clearCache($className, $clearMappingCache, true);
                }
            }
        }
    }

    final public function getObjectMapper(): ObjectMapper
    {
        if (!isset($this->objectMapper)) {
            $this->objectMapper = $this->createObjectMapper();
        }

        return $this->objectMapper;
    }

    final protected function getEntityTracker(): EntityTracker
    {
        if (!isset($this->entityTracker)) {
            $this->entityTracker = $this->createEntityTracker();
        }

        return $this->entityTracker;
    }

    final protected function getQueryHelper(): InternalQueryHelper
    {
        if (!isset($this->queryHelper)) {
            $this->queryHelper = $this->createQueryHelper();
        }

        return $this->queryHelper;
    }

    final protected function getStorage(): StorageInterface
    {
        if (!isset($this->storage)) {
            $this->storage = $this->createStorage();
        }

        return $this->storage;
    }

    /**
     * @param ConfigOptions|null $configOptions
     * @return ProxyFactory
     * @throws ObjectiphyException
     */
    final protected function getProxyFactory(?ConfigOptions $configOptions): ProxyFactory
    {
        if (!isset($this->proxyFactory)) {
            $this->proxyFactory = $this->createProxyFactory($configOptions);
        }

        return $this->proxyFactory;
    }
    
    final protected function createObjectMapper(): ObjectMapper
    {
        return new ObjectMapper($this->getMappingProvider(), $this->createNameResolver(), $this->getCache());
    }

    /**
     * @param ConfigOptions|null $configOptions
     * @return ObjectFetcher
     * @throws ObjectiphyException
     */
    final protected function createObjectFetcher(?ConfigOptions $configOptions = null): ObjectFetcher
    {
        $sqlSelector = $this->getSqlSelector();
        $objectMapper = $this->getObjectMapper();
        $objectBinder = $this->createObjectBinder($configOptions);
        $entityTracker = $this->getEntityTracker();
        $stringReplacer = $this->getSqlStringReplacer();
        $explanation = $this->getExplanation();
        $storage = $this->getStorage();

        return new ObjectFetcher($sqlSelector, $objectMapper, $objectBinder, $storage, $entityTracker, $stringReplacer, $explanation);
    }

    final protected function createCache(): CacheInterface
    {
        return new FileCache($this->configOptions->cacheDirectory);
    }
    
    final protected function createNameResolver(): NameResolver
    {
        return new NameResolver();
    }

    final protected function createObjectPersister(): ObjectPersister
    {
        $sqlUpdater = $this->getSqlUpdater();
        $objectMapper = $this->getObjectMapper();
        $objectUnbinder = $this->createObjectUnbinder();
        $storage = $this->getStorage();
        $entityTracker = $this->getEntityTracker();
        $stringReplacer = $this->getSqlStringReplacer();
        $explanation = $this->getExplanation();

        return new ObjectPersister($sqlUpdater, $objectMapper, $objectUnbinder, $storage, $entityTracker, $stringReplacer, $explanation);
    }

    final protected function createObjectRemover(): ObjectRemover
    {
        $objectMapper = $this->getObjectMapper();
        $sqlDeleter = $this->getSqlDeleter();
        $storage = $this->getStorage();
        $entityTracker = $this->getEntityTracker();
        $queryHelper = $this->getQueryHelper();
        $objectFetcher = $this->createObjectFetcher(); //New instance for different findOptions
        $stringReplacer = $this->getSqlStringReplacer();
        $explanation = $this->getExplanation();

        return new ObjectRemover($objectMapper, $sqlDeleter, $storage, $objectFetcher, $entityTracker, $queryHelper, $stringReplacer, $explanation);
    }

    final protected function createEntityTracker(): EntityTracker
    {
        return new EntityTracker();
    }

    final protected function createQueryHelper(): InternalQueryHelper
    {
        return new InternalQueryHelper($this->getObjectMapper(), $this->getSqlStringReplacer());
    }

    /**
     * @param ConfigOptions|null $configOptions
     * @return ObjectBinder
     * @throws ObjectiphyException
     */
    final protected function createObjectBinder(?ConfigOptions $configOptions = null): ObjectBinder
    {
        $entityFactory = $this->createEntityFactory($configOptions);
        $entityTracker = $this->getEntityTracker();
        $dataTypeHandler = $this->getDataTypeHandlerMySql();
        $collectionFactory = $this->getCollectionFactory();
        $sqlStringReplacer = $this->getSqlStringReplacer();
        $queryHelper = $this->getQueryHelper();

        return new ObjectBinder($this, $entityFactory, $entityTracker, $dataTypeHandler, $collectionFactory, $sqlStringReplacer, $queryHelper);
    }

    final protected function createObjectUnbinder(): ObjectUnbinder
    {
        $dataTypeHandler = $this->getDataTypeHandlerMySql();
        $objectMapper = $this->getObjectMapper();
        return new ObjectUnbinder($this->getEntityTracker(), $dataTypeHandler, $objectMapper);
    }

    /**
     * @param ConfigOptions|null $configOptions
     * @return EntityFactoryInterface
     * @throws ObjectiphyException
     */
    final protected function createEntityFactory(?ConfigOptions $configOptions = null): EntityFactoryInterface
    {
        return new EntityFactory($this->createProxyFactory($configOptions));
    }

    /**
     * @param ConfigOptions|null $configOptions
     * @return ProxyFactory
     * @throws ObjectiphyException
     */
    final protected function createProxyFactory(?ConfigOptions $configOptions = null): ProxyFactory
    {
        $configOptions ??= $this->configOptions;
        return new ProxyFactory($configOptions->devMode, $configOptions->cacheDirectory);
    }
    
    final protected function createStorage(): StorageInterface
    {
        return new PdoStorage($this->pdo);
    }

    /**
     * Check if a custom repository class is required for the given entity (always defer to the value passed in though,
     * if present).
     * @param string|null $repositoryClassName Name of repository class passed in.
     * @param string|null $entityClassName
     * @return string Name of repository class to use.
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function getRepositoryClassName(
        ?string $repositoryClassName = null, 
        ?string $entityClassName = null
    ): string {
        $mappingFound = false;
        $repositoryClassName = $repositoryClassName ?? ObjectRepository::class;
        if ($this->validateEntityClass($entityClassName) && $repositoryClassName == ObjectRepository::class) {
            //Check for an annotation or other mapping definition that specifies a custom repository
            $entityReflectionClass = new \ReflectionClass($entityClassName);
            $classMapping = $this->getObjectMapper()->getTableMapping($entityReflectionClass, false, $mappingFound);
            $repositoryClassName = $classMapping->repositoryClassName;
        }
        $useCustomClass = $this->validateCustomRepository($repositoryClassName, $entityClassName, $mappingFound);
        $repositoryClassName = $useCustomClass ? $repositoryClassName : ObjectRepository::class;

        return '\\' . ltrim($repositoryClassName, '\\');
    }

    /**
     * @param string|null $entityClassName
     * @return bool
     * @throws ObjectiphyException
     */
    private function validateEntityClass(?string $entityClassName = null): bool
    {
        if ($entityClassName && !class_exists($entityClassName)) {
            throw new ObjectiphyException(sprintf('Entity class %1$s does not exist.', $entityClassName));
        }

        return $entityClassName ? true : false;
    }

    /**
     * @param string $repositoryClassName
     * @param string $entityClassName
     * @param bool $wasMapped
     * @return bool
     * @throws ObjectiphyException
     */
    private function validateCustomRepository(
        string $repositoryClassName,
        string $entityClassName,
        bool $wasMapped
    ): bool {
        if ($repositoryClassName && !class_exists($repositoryClassName)) {
            if ($wasMapped) {
                $errorMessage = sprintf(
                    'Custom repository class %1$s which was specified in the mapping for entity %2$s does not exist.',
                    $repositoryClassName,
                    $entityClassName
                );
            } else {
                $errorMessage = sprintf(
                    'Custom repository class %1$s does not exist.',
                    $repositoryClassName
                );
            }
            throw new ObjectiphyException($errorMessage);
        }

        return $repositoryClassName ? true : false;
    }

    private function getExplanation()
    {
        $this->explanation ??= new Explanation($this->getSqlStringReplacer());
        return $this->explanation;
    }
    
    private function getSqlSelector(): SqlSelectorInterface
    {
        if (!isset($this->sqlSelector)) {
            $stringReplacer = $this->getSqlStringReplacer();
            $joinProvider = $this->getJoinProviderMySql();
            $whereProvider = $this->getWhereProviderMySql();
            $this->sqlSelector = new SqlSelectorMySql($stringReplacer, $joinProvider, $whereProvider);
        }
        
        return $this->sqlSelector;
    }

    private function getSqlUpdater(): SqlUpdaterInterface
    {
        if (!isset($this->sqlUpdater)) {
            $stringReplacer = $this->getSqlStringReplacer();
            $joinProvider = $this->getJoinProviderMySql();
            $whereProvider = $this->getWhereProviderMySql();

            $this->sqlUpdater = new SqlUpdaterMySql($stringReplacer, $joinProvider, $whereProvider);
        }

        return $this->sqlUpdater;
    }

    private function getSqlDeleter(): SqlDeleterInterface
    {
        if (!isset($this->sqlDeleter)) {
            $stringReplacer = $this->getSqlStringReplacer();
            $joinProvider = $this->getJoinProviderMySql();
            $whereProvider = $this->getWhereProviderMySql();
            $this->sqlDeleter = new SqlDeleterMySql($stringReplacer, $joinProvider, $whereProvider);
        }

        return $this->sqlDeleter;
    }

    private function getObjectRemover(): ObjectRemover
    {
        if (!isset($this->objectRemover)) {
            $this->objectRemover = $this->createObjectRemover();
        }

        return $this->objectRemover;
    }

    private function getJoinProviderMySql(): JoinProviderMySql
    {
        if (!isset($this->joinProvider)) {
            $this->joinProvider = new JoinProviderMySql($this->getSqlStringReplacer(), $this->getObjectMapper());
        }

        return $this->joinProvider;
    }

    private function getWhereProviderMySql(): WhereProviderMySql
    {
        if (!isset($this->whereProvider)) {
            $this->whereProvider = new WhereProviderMySql($this->getSqlStringReplacer());
        }

        return $this->whereProvider;
    }

    private function getSqlStringReplacer(): SqlStringReplacer
    {
        if (!isset($this->stringReplacer)) {
            $this->stringReplacer = new SqlStringReplacer($this->getObjectMapper(), $this->getDataTypeHandlerMySql());
        }

        return $this->stringReplacer;
    }

    private function getDataTypeHandlerMySql(): DataTypeHandlerInterface
    {
        if (!isset($this->dataTypeHandler)) {
            $this->dataTypeHandler = new DataTypeHandlerMySql();
        }

        return $this->dataTypeHandler;
    }

    private function getCollectionFactory(): CollectionFactoryInterface
    {
        if (!isset($this->collectionFactory)) {
            $this->collectionFactory = new CollectionFactory();
        }

        return $this->collectionFactory;
    }
}
