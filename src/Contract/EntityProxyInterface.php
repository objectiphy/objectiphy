<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for an Entity proxy that uses lazy loading.
 */
interface EntityProxyInterface
{
    public function setLazyLoader(string $propertyName, \Closure $closure): void;
    public function isChildAsleep(string $propertyName): bool;
    public function triggerLazyLoad(string $propertyName): void;
    public function getClassName(): string;
    public function setPrivatePropertyValue(string $propertyName, $value): bool;
    public function getPrivatePropertyValue(string $propertyName, bool &$wasFound);
}
