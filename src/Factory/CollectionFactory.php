<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class CollectionFactory implements CollectionFactoryInterface
{
    public function createCollection(string $collectionClassName, array $entities): iterable
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
