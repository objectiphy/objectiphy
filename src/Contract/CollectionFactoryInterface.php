<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface CollectionFactoryInterface
{
    /**
     * Return a custom collection object to store child entities (eg. an ArrayCollection).
     * @param string $collectionClassName
     * @param array $entities
     * @return iterable
     */
    public function createCollection(string $collectionClassName, array $entities): iterable;
}
