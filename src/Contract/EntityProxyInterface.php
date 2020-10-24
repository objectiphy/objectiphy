<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * Interface for an Entity proxy that uses lazy loading.
 */
interface EntityProxyInterface
{
    public function setLazyLoader(string $propertyName, \Closure $closure): void;
    public function isChildAsleep(string $propertyName): bool;
    public function triggerLazyLoad(string $propertyName): void;
    public function getClassName(): string;
}