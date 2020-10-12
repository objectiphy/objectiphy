<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Annotations\DocParser;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Database\PdoStorage;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\ObjectMapper;
use Objectiphy\Objectiphy\MappingProvider\MappingProvider;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderAnnotation;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderDoctrineAnnotation;
use Objectiphy\Objectiphy\Database\SqlBuilderInterface;
use Objectiphy\Objectiphy\Database\SqlBuilderMySql;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class RepositoryFactory
{
    private \PDO $pdo;
    private ConfigOptions $configOptions;
    private MappingProviderInterface $mappingProvider;
    private SqlBuilderInterface $sqlBuilder;
    
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

    public function setSqlBuilder(SqlBuilderInterface $sqlBuilder)
    {
        $this->sqlBuilder = $sqlBuilder;
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
            $annotationReader->setClassNameAttributes(['childClassName', 'targetEntity']);
            $baseMappingProvider = new MappingProvider();
            $doctrineMappingProvider = new MappingProviderDoctrineAnnotation($baseMappingProvider, $annotationReader);
            $this->mappingProvider = new MappingProviderAnnotation($doctrineMappingProvider, $annotationReader);
        }

        return $this->mappingProvider;
    }

    public function createRepository(
        string $entityClassName = '',
        string $repositoryClassName = null,
        EntityFactoryInterface $entityFactory = null,
        ?ConfigOptions $configOptions = null
    ) {
        $repositoryClassName = $this->getRepositoryClassName($repositoryClassName, $entityClassName);

        /** @var ObjectRepository $objectRepository */
        $objectRepository = new $repositoryClassName(
            $this->createObjectMapper(),
            $this->createObjectFetcher(),
            $this->createObjectPersister(),
            $this->createObjectRemover(),
            $configOptions ?? $this->configOptions
        );
        if ($entityClassName) {
            $objectRepository->setClassName($entityClassName, $entityFactory);
        }

        return $objectRepository;
    }

    protected final function createObjectMapper()
    {
        return new ObjectMapper($this->mappingProvider);
    }

    protected final function createObjectFetcher()
    {
        $sqlBuilder = $this->getSqlBuilder();
        $objectMapper = $this->createObjectMapper();
        $objectBinder = $this->createObjectBinder();
        $storage = $this->createStorage();

        return new ObjectFetcher($sqlBuilder, $objectMapper, $objectBinder, $storage);
    }

    protected final function createObjectPersister()
    {
        return new ObjectPersister($this->getSqlBuilder());
    }

    protected final function createObjectRemover()
    {
        return new ObjectRemover($this->getSqlBuilder());
    }

    protected final function createObjectBinder()
    {
        return new ObjectBinder();
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
    
    private function getSqlBuilder()
    {
        if (!isset($this->sqlBuilder)) {
            $this->sqlBuilder = new SqlBuilderMySql();
        }
        
        return $this->sqlBuilder;
    }
}
