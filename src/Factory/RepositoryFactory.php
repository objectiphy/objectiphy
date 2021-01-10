<?php

/**
 * RSW: TODO: Make this better. Possibly create objects in a generic way (kinda like autowiring), or split into smaller factories.
 */
declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\ObjectRepositoryInterface;
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

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class RepositoryFactory
{
    private \PDO $pdo;
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
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;
    private ObjectRemover $objectRemover;
    private ?ExplanationInterface $explanation = null;
    /**
     * @var ObjectRepositoryInterface[]
     */
    private array $repositories = [];

    public function __construct(\PDO $pdo, ?ConfigOptions $configOptions = null)
    {
        $this->pdo = $pdo;
        if (!$configOptions) {
            $configOptions = new ConfigOptions();
        }
        $this->setConfigOptions($configOptions);
    }
    
    public function setConfigOptions(ConfigOptions $configOptions): void
    {
        $this->configOptions = $configOptions;
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
    
    public function setSqlBuilder(SqlSelectorInterface $sqlSelector, DataTypeHandlerInterface $dataTypeHandler): void
    {
        $this->sqlSelector = $sqlSelector;
        $this->dataTypeHandler = $dataTypeHandler;
    }

    public function setMappingProvider(MappingProviderInterface $mappingProvider): void
    {
        $this->mappingProvider = $mappingProvider;
    }

    /**
     * If no custom mapping provider has been set, return the default one, which reads both Doctrine and Objectiphy
     * annotations.
     * @return MappingProviderInterface
     */
    public function getMappingProvider(): MappingProviderInterface
    {
        if (!isset($this->mappingProvider)) {
            //Decorate a mapping provider for Doctrine/Objectiphy annotations
            $annotationReader = new AnnotationReader();
            $annotationReader->setClassNameAttributes([
                'childClassName',
                'targetEntity',
                'collectionClass',
                'collectionFactoryClass'
            ]);
            $baseMappingProvider = new MappingProvider();
            $doctrineMappingProvider = new MappingProviderDoctrineAnnotation($baseMappingProvider, $annotationReader);
            $this->mappingProvider = new MappingProviderAnnotation($doctrineMappingProvider, $annotationReader);
            $this->mappingProvider->setThrowExceptions(!$this->configOptions->productionMode);
        }

        return $this->mappingProvider;
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
                $configOptions
            );
            if ($entityClassName) {
                $objectRepository->setClassName($entityClassName);
            }
            $this->repositories[$entityClassName][$configHash] = $objectRepository;
        }

        return $this->repositories[$entityClassName][$configHash];
    }

    final protected function getObjectMapper(): ObjectMapper
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

    final protected function getStorage(): StorageInterface
    {
        if (!isset($this->storage)) {
            $this->storage = $this->createStorage();
        }

        return $this->storage;
    }

    final protected function getProxyFactory(?ConfigOptions $configOptions): ProxyFactory
    {
        if (!isset($this->proxyFactory)) {
            $this->proxyFactory = $this->createProxyFactory($configOptions);
        }

        return $this->proxyFactory;
    }
    
    final protected function createObjectMapper(): ObjectMapper
    {
        return new ObjectMapper($this->getMappingProvider(), $this->createNameResolver());
    }

    final protected function createObjectFetcher(?ConfigOptions $configOptions = null): ObjectFetcher
    {
        $sqlSelector = $this->getSqlSelector();
        $objectMapper = $this->getObjectMapper();
        $objectBinder = $this->createObjectBinder($configOptions);
        $entityTracker = $this->getEntityTracker();
        $explanation = $this->getExplanation();
        $storage = $this->getStorage();

        return new ObjectFetcher($sqlSelector, $objectMapper, $objectBinder, $storage, $entityTracker, $explanation);
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
        $explanation = $this->getExplanation();

        return new ObjectPersister($sqlUpdater, $objectMapper, $objectUnbinder, $storage, $entityTracker, $explanation);
    }

    final protected function createObjectRemover(): ObjectRemover
    {
        $objectMapper = $this->getObjectMapper();
        $sqlDeleter = $this->getSqlDeleter();
        $storage = $this->getStorage();
        $entityTracker = $this->getEntityTracker();
        $objectFetcher = $this->createObjectFetcher(); //New instance for different findOptions
        $explanation = $this->getExplanation();

        return new ObjectRemover($objectMapper, $sqlDeleter, $storage, $objectFetcher, $entityTracker, $explanation);
    }

    final protected function createEntityTracker(): EntityTracker
    {
        return new EntityTracker();
    }

    final protected function createObjectBinder(?ConfigOptions $configOptions = null): ObjectBinder
    {
        $entityFactory = $this->createEntityFactory($configOptions);
        $entityTracker = $this->getEntityTracker();
        $dataTypeHandler = $this->getDataTypeHandlerMySql();

        return new ObjectBinder($this, $entityFactory, $entityTracker, $dataTypeHandler);
    }

    final protected function createObjectUnbinder(): ObjectUnbinder
    {
        $dataTypeHandler = $this->getDataTypeHandlerMySql();
        $objectMapper = $this->getObjectMapper();
        return new ObjectUnbinder($this->getEntityTracker(), $dataTypeHandler, $objectMapper);
    }
    
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
        return new ProxyFactory($configOptions->productionMode, $configOptions->cacheDirectory);
    }
    
    final protected function createStorage(): StorageInterface
    {
        return new PdoStorage($this->pdo);
    }

    final protected function createSqlUpdater(): SqlUpdaterInterface
    {
        $dataTypeHandler = $this->getDataTypeHandlerMySql();
        $joinProvider = $this->getJoinProviderMySql();
        $whereProvider = $this->getWhereProviderMySql();
        return new SqlUpdaterMySql($dataTypeHandler, $joinProvider, $whereProvider);
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
        $this->explanation ??= new Explanation();
        return $this->explanation;
    }
    
    private function getSqlSelector(): SqlSelectorInterface
    {
        if (!isset($this->sqlSelector)) {
            $dataTypeHandler = $this->getDataTypeHandlerMySql();
            $joinProvider = $this->getJoinProviderMySql();
            $whereProvider = $this->getWhereProviderMySql();
            $this->sqlSelector = new SqlSelectorMySql($dataTypeHandler, $joinProvider, $whereProvider);
        }
        
        return $this->sqlSelector;
    }

    private function getSqlUpdater(): SqlUpdaterInterface
    {
        if (!isset($this->sqlUpdater)) {
            $this->sqlUpdater = $this->createSqlUpdater();
        }

        return $this->sqlUpdater;
    }

    private function getSqlDeleter(): SqlDeleterInterface
    {
        if (!isset($this->sqlDeleter)) {
            $joinProvider = $this->getJoinProviderMySql();
            $whereProvider = $this->getWhereProviderMySql();
            $dataTypeHandler = $this->getDataTypeHandlerMySql();
            $this->sqlDeleter = new SqlDeleterMySql($dataTypeHandler, $joinProvider, $whereProvider);
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
            $this->joinProvider = new JoinProviderMySql($this->getDataTypeHandlerMySql());
        }

        return $this->joinProvider;
    }

    private function getWhereProviderMySql(): WhereProviderMySql
    {
        if (!isset($this->whereProvider)) {
            $this->whereProvider = new WhereProviderMySql($this->getDataTypeHandlerMySql());
        }

        return $this->whereProvider;
    }

    private function getDataTypeHandlerMySql(): DataTypeHandlerInterface
    {
        if (!isset($this->dataTypeHandler)) {
            $this->dataTypeHandler = new DataTypeHandlerMySql();
        }

        return $this->dataTypeHandler;
    }
}
