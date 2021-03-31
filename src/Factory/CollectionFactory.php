<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class CollectionFactory implements CollectionFactoryInterface
{
    /**
     * Return a custom collection object to store child entities (eg. an ArrayCollection).
     * @param string $collectionClassName Name of the collection class (obviously)
     * @param array $entities The items that need to be added to the collection
     * @param string $parentEntityClassName The name of the parent entity class that holds the -to-many association
     * @param string $parentPropertyName The name of the property on the parent entity that is being populated
     * NOTE: You do not need to populate this in your factory, it is just provided in case you want to do something
     * different for different properties. Objectiphy will populate the property with the collection you return.
     * @return iterable
     */
    public function createCollection(
        string $collectionClassName,
        array $entities,
        string $parentEntityClassName,
        string $parentPropertyName
    ): iterable {
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
