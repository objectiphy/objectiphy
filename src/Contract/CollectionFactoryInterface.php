<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

interface CollectionFactoryInterface
{
    /**
     * Return a custom collection object to store child entities (eg. an ArrayCollection).
     * @param array $entities
     * @return \Traversable
     */
    public function createCollection(string $collectionClassName, array $entities): \Traversable;
}
