<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy;

/**
 * If you want Objectiphy to use a factory to create your entities, use this interface.
 */
interface EntityFactoryInterface
{
    public function createEntity($entityName = null);
}
