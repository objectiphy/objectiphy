<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Annotations\DocParser;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\ObjectMapper;
use Objectiphy\Objectiphy\MappingProvider\MappingProvider;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderAnnotation;
use Objectiphy\Objectiphy\MappingProvider\MappingProviderDoctrineAnnotation;

class RepositoryFactoryMySql
{
    private \PDO $pdo;
    private ConfigOptions $configCoptions;
    private MappingProviderInterface $mappingProvider;
    
    public function __construct(\PDO $pdo, ConfigOptions $configOptions)
    {
        $this->pdo = $pdo;
        $this->configCoptions = $configOptions;
    }

    public function setMappingProvider(MappingProviderInterface $mappingProvider)
    {
        $this->mappingProvider = $mappingProvider;
    }

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
        array $tableOverrides = []
    ) {
        $repositoryClassName = $this->getRepositoryClassName($repositoryClassName, $entityClassName);

        /** @var ObjectRepository $objectRepository */
        $objectRepository = new $repositoryClassName($storageQueryBuilder, $objectBinder, $this->storage, $proxyFactory, $objectFetcher, $objectPersister, $objectRemover);
        if ($entityClassName) {
            $objectRepository->setEntityClassName($entityClassName, $entityFactory);
        }
        $objectRepository->setTableOverrides($tableOverrides);
        $this->repositoriesCreated[] = $objectRepository;

        return $objectRepository;
    }

    /**
     * Check if a custom repository class is required for the given entity (always defer to the value passed in though,
     * if present).
     * @param string $repositoryClassName Name of repository class passed in.
     * @param string $entityClassName
     * @return string Name of repository class to use.
     * @throws ObjectiphyException
     */
    private function getRepositoryClassName(string $repositoryClassName, string $entityClassName)
    {
        if ($repositoryClassName && !class_exists($repositoryClassName)) {
            throw new ObjectiphyException('Custom repository class does not exist: ' . $repositoryClassName);
        }
        $repositoryClassName = $repositoryClassName ?? ObjectRepository::class;
        if ($entityClassName && $repositoryClassName == ObjectRepository::class) {
            //Check for an annotation or other mapping definition that specifies a custom repository
            $classMapping = $this->mappingProvider->getClassMapping($entityClassName);
            if ($classMapping && $classMapping->repositoryClassName && class_exists($classMapping->repositoryClassName)) {
                $repositoryClassName = $classMapping->repositoryClassName;
            }
        }

        return $repositoryClassName;
    }
}
