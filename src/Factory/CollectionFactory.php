<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;

class CollectionFactory implements CollectionFactoryInterface
{
    public function createCollection(string $collectionClassName, array $entities): \Traversable
    {
        try {
            $collection = $entities;
            if ($collectionClassName && $collectionClassName != 'array') {
                $collection = new $collectionClassName($entities);
            }
        } finally {
            return $collection;
        }
    }
}
