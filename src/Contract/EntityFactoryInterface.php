<?php

namespace Objectiphy\Objectiphy;

/**
 * If you want Objectiphy to use a factory to create your entities, use this interface.
 */
interface EntityFactoryInterface
{
    public function createEntity($entityName = null);
}
