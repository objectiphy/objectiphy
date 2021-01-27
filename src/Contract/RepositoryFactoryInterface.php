<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\ConfigOptions;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for the main repository factory.
 */
interface RepositoryFactoryInterface
{
    /**
     * @param array $configOptions Keyed by option name
     */
    public function setConfigOptions(array $configOptions): void;

    public function reset(): void;

    public function setSqlBuilder(SqlSelectorInterface $sqlSelector, DataTypeHandlerInterface $dataTypeHandler): void;

    public function setMappingProvider(MappingProviderInterface $mappingProvider): void;

    public function getMappingProvider(): MappingProviderInterface;

    public function setExplanation(ExplanationInterface $explanation);

    public function createRepository(
        string $entityClassName = '',
        string $repositoryClassName = null,
        ?ConfigOptions $configOptions = null,
        bool $resetFirst = false
    ): ObjectRepositoryInterface;

    public function clearCache(?string $className = null, bool $clearMappingCache = true): void;
}
