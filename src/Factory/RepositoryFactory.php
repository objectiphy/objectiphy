<?php

/**
 * @todo Make this better. Possibly create objects in a generic way (autowiring), or split into smaller factories.
 */
declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Annotations\DocParser;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\StorageInterface;
use Objectiphy\Objectiphy\Database\DataTypeHandlerMySql;
use Objectiphy\Objectiphy\Database\PdoStorage;
use Objectiphy\Objectiphy\Database\SelectorMySql;
use Objectiphy\Objectiphy\Database\SqlSelectorMySql;
use Objectiphy\Objectiphy\Database\SqlUpdaterMySql;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\MappingProvider\MappingProvider;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderAnnotation;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderDoctrineAnnotation;
use Objectiphy\Objectiphy\Database\SqlBuilderMySql;
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
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class RepositoryFactory
{
    private \PDO $pdo;
    private ConfigOptions $configOptions;
    private MappingProviderInterface $mappingProvider;
    private SqlSelectorInterface $sqlSelector;
    private SqlUpdaterInterface $sqlUpdater;
    private DataTypeHandlerInterface $dataTypeHandler;
    private ObjectMapper $objectMapper;
    private StorageInterface $storage;
    private EntityTracker $entityTracker;
    private array $repositories = [];

    public function __construct(\PDO $pdo, ?ConfigOptions $configOptions = null)
    {
        $this->pdo = $pdo;
        if (!$configOptions) {
            $configOptions = new ConfigOptions();
        }
        $this->setConfigOptions($configOptions);
    }

    public function setConfigOptions(ConfigOptions $configOptions)
    {
        $this->configOptions = $configOptions;
    }

    public function setSqlBuilder(SqlSelectorInterface $sqlSelector, DataTypeHandlerInterface $dataTypeHandler)
    {
        $this->sqlSelector = $sqlSelector;
        $this->dataTypeHandler = $dataTypeHandler;
    }

    public function setMappingProvider(MappingProviderInterface $mappingProvider)
    {
        $this->mappingProvider = $mappingProvider;
    }

    /**
     * If no custom mapping provider has been set, return the default one, which reads both Doctrine and Objectiphy
     * annotations.
     * @return MappingProviderInterface|MappingProviderAnnotation
     */
    public function getMappingProvider()
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

    public function createRepository(
        string $entityClassName = '',
        string $repositoryClassName = null,
        ?ConfigOptions $configOptions = null
    ) {
        $configOptions ??= $this->configOptions;
        $configHash = $configOptions->getHash($repositoryClassName ?: '');
        if (!isset($this->repositories[$entityClassName][$configHash])) {
            $repositoryClassName = $this->getRepositoryClassName($repositoryClassName, $entityClassName);

            /** @var ObjectRepository $objectRepository */
            $objectRepository = new $repositoryClassName(
                $this->getObjectMapper(),
                $this->createObjectFetcher($configOptions),
                $this->createObjectPersister(),
                $this->createObjectRemover(),
                $configOptions
            );
            if ($entityClassName) {
                $objectRepository->setClassName($entityClassName);
            }
            $this->repositories[$entityClassName][$configHash] = $objectRepository;
        }

        return $this->repositories[$entityClassName][$configHash];
    }

    protected final function getObjectMapper()
    {
        if (!isset($this->objectMapper)) {
            $this->objectMapper = $this->createObjectMapper();
        }

        return $this->objectMapper;
    }

    protected final function getEntityTracker()
    {
        if (!isset($this->entityTracker)) {
            $this->entityTracker = $this->createEntityTracker();
        }

        return $this->entityTracker;
    }

    protected final function getStorage()
    {
        if (!isset($this->storage)) {
            $this->storage = $this->createStorage();
        }

        return $this->storage;
    }
    
    protected final function createObjectMapper()
    {
        return new ObjectMapper($this->getMappingProvider(), $this->createNameResolver());
    }

    protected final function createObjectFetcher(?ConfigOptions $configOptions = null)
    {
        $sqlSelector = $this->getSqlSelector();
        $objectMapper = $this->getObjectMapper();
        $objectBinder = $this->createObjectBinder($configOptions);
        $entityTracker = $this->getEntityTracker();
        $storage = $this->getStorage();

        return new ObjectFetcher($sqlSelector, $objectMapper, $objectBinder, $storage, $entityTracker);
    }

    protected final function createNameResolver()
    {
        return new NameResolver();
    }

    protected final function createObjectPersister()
    {
        $sqlUpdater = $this->getSqlUpdater();
        $objectMapper = $this->getObjectMapper();
        $objectUnbinder = $this->createObjectUnbinder();
        $storage = $this->getStorage();
        $entityTracker = $this->getEntityTracker();
        return new ObjectPersister($sqlUpdater, $objectMapper, $objectUnbinder, $storage, $entityTracker);
    }

    protected final function createObjectRemover()
    {
        return new ObjectRemover();
    }

    protected final function createEntityTracker()
    {
        return new EntityTracker();
    }

    protected final function createObjectBinder(?ConfigOptions $configOptions = null)
    {
        $entityFactory = $this->createEntityFactory($configOptions);
        $entityTracker = $this->getEntityTracker();
        $dataTypeHandler = $this->getDataTypeHandler();
        return new ObjectBinder($this, $entityFactory, $entityTracker, $dataTypeHandler);
    }

    protected final function createObjectUnbinder()
    {
        $dataTypeHandler = $this->getDataTypeHandler();
        return new ObjectUnbinder($this->getEntityTracker(), $dataTypeHandler);
    }
    
    protected final function createEntityFactory(?ConfigOptions $configOptions = null)
    {
        return new EntityFactory($this->createProxyFactory($configOptions));
    }

    protected final function createProxyFactory(?ConfigOptions $configOptions = null)
    {
        $configOptions ??= $this->configOptions;
        return new ProxyFactory($configOptions->productionMode, $configOptions->cacheDirectory);
    }
    
    protected final function createStorage()
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
     */
    private function getRepositoryClassName(?string $repositoryClassName = null, ?string $entityClassName = null)
    {
        $mappingFound = false;
        $repositoryClassName = $repositoryClassName ?? ObjectRepository::class;
        if ($this->validateEntityClass($entityClassName) && $repositoryClassName == ObjectRepository::class) {
            //Check for an annotation or other mapping definition that specifies a custom repository
            $entityReflectionClass = new \ReflectionClass($entityClassName);
            $classMapping = $this->getMappingProvider()->getTableMapping($entityReflectionClass, $mappingFound);
            $repositoryClassName = $classMapping->repositoryClassName;
        }
        $useCustomClass = $this->validateCustomRepository($repositoryClassName, $mappingFound);
        $repositoryClassName = $useCustomClass ? $repositoryClassName : ObjectRepository::class;

        return '\\' . ltrim($repositoryClassName, '\\');
    }

    private function validateEntityClass(?string $entityClassName = null)
    {
        if ($entityClassName && !class_exists($entityClassName)) {
            throw new ObjectiphyException(sprintf('Entity class %1$s does not exist.', $entityClassName));
        }

        return $entityClassName ? true : false;
    }

    private function validateCustomRepository(string $repositoryClassName, bool $wasMapped)
    {
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
    
    private function getSqlSelector()
    {
        if (!isset($this->sqlSelector)) {
            $this->sqlSelector = new SqlSelectorMySql();
        }
        
        return $this->sqlSelector;
    }

    private function getSqlUpdater()
    {
        if (!isset($this->sqlUpdater)) {
            $this->sqlUpdater = new SqlUpdaterMySql();
        }

        return $this->sqlUpdater;
    }

    private function getDataTypeHandler()
    {
        if (!isset($this->dataTypeHandler)) {
            $this->dataTypeHandler = new DataTypeHandlerMySql();
        }

        return $this->dataTypeHandler;
    }
}
