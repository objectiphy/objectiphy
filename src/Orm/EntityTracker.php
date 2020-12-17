<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\EntityProxyInterface;

/**
 * A local cache of entities retrieved, and a cloned copy of each for tracking changes.
 */
class EntityTracker
{
    private array $entities = [];
    private array $clones = [];
    private array $trackedChanges = [];

    /**
     * Store an entity in the tracker, and make a shallow clone to track for changes.
     */
    public function storeEntity(object $entity, array $pkValues): void
    {
        $pkIndex = $this->getIndexForPk($pkValues);
        if (strlen($pkIndex) > 2) {
            $entityClass = ObjectHelper::getObjectClassName($entity);
            $this->entities[$entityClass][$pkIndex] = $entity;
            try {
                $this->clones[$entityClass][$pkIndex] = $this->cloneEntity($entity);
            } catch (\Throwable $ex) {
                //Not the end of the world, we'll just assume it is dirty
            }
        }
    }

    /**
     * Check whether the tracker holds an instance of the given object (either the object itself,
     * or class name with the given primary key values.
     */
    public function hasEntity($entityOrClassName, array $pkValues = []): bool
    {
        if (is_string($entityOrClassName)) {
            $pkIndex = $this->getIndexForPk($pkValues);
            if (strlen($pkIndex) > 2) {
                return isset($this->entities[$entityOrClassName][$pkIndex]);
            }
        } elseif (is_object($entityOrClassName)) {
            $className = ObjectHelper::getObjectClassName($entityOrClassName);
            if (in_array($entityOrClassName, $this->entities[$className])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve an existing entity from the tracker.
     */
    public function getEntity(string $className, array $pkValues): ?object
    {
        $pkIndex = $this->getIndexForPk($pkValues);
        return $this->entities[$className][$pkIndex] ?? null;
    }

    /**
     * @return bool Whether or not anything has changed on the given entity (if unknown, returns true)
     */
    public function isEntityDirty(object $entity): bool
    {
        $entityClass = ObjectHelper::getObjectClassName($entity);
        if (isset($this->trackedChanges[$entityClass])) {
            return true;
        } elseif (isset($this->clones[$entityClass])) {
            return $this->clones[$entityClass] == $entity;
        } else {
            return true;
        }
    }

    /**
     * Return list of properties and values that may need updating. If we are tracking the entity, 
     * only values that have changed will be returned - otherwise, all properties will be returned.
     * The return values are keyed by property name
     */
    public function getDirtyProperties(object $entity, array $pkValues): array
    {
        $changes = [];
        $className = ObjectHelper::getObjectClassName($entity);
        $pkIndex = $this->getIndexForPk($pkValues);
        if (isset($this->trackedChanges[$className][$pkIndex])) {
            return $this->trackedChanges[$className];
        }
        $clone = $this->clones[$className][$pkIndex] ?? null;
        $reflectionClass = new \ReflectionClass($className);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $property = $reflectionProperty->getName();
            if (!($entity instanceof EntityProxyInterface) || !$entity->isChildAsleep($property)) { //Shh. Don't wake up the kids.
                $entityValue = ObjectHelper::getValueFromObject($entity, $property);
                $notFound = '**!VALUE_NOT_FOUND!**';
                $cloneValue = $clone ? ObjectHelper::getValueFromObject($clone, $property, $notFound) : $notFound;
                if ($entityValue != $cloneValue) {
                    $changes[$property] = $entityValue;
                }
            }
        }
        
        return $changes;
    }

    public function getRemovedChildren(object $entity, string $propertyName, array $childPks): array
    {
        $removedChildren = [];
        $pkIndex = '';
        $className = ObjectHelper::getObjectClassName($entity);
        foreach ($this->entities[$className] as $pkIndex => $storedEntity) {
            if ($storedEntity == $entity) {
                break;
            }
        }

        if ($pkIndex) {
            $clone = $this->clones[$className][$pkIndex] ?? null;
            $clonedCollection = ObjectHelper::getValueFromObject($clone, $propertyName, null, true, true) ?? [];
            $entityCollection = ObjectHelper::getValueFromObject($entity, $propertyName) ?? [];
            foreach ($clonedCollection as $clonedChildItem) {
                $pkValueMatch = false;
                foreach ($childPks as $childPk) {
                    $clonePkValue = ObjectHelper::getValueFromObject($clonedChildItem, $childPk, null, true, true);
                    $pkValueMatch = false;
                    foreach ($entityCollection as $childItem) {
                        $pkValue = ObjectHelper::getValueFromObject($childItem, $childPk);
                        if ($pkValue == $clonePkValue) {
                            $pkValueMatch = true;
                            break;
                        }
                    }
                }
                if (!$pkValueMatch) {
                    $removedChildren[] = $clonedChildItem;
                }
            }
        }

        return $removedChildren;
    }

    /**
     * Stop tracking entities - either just for the given class, or everything if omitted.
     * @param string|null $className
     */
    public function clear(?string $className = null): void
    {
        if ($className) {
            unset($this->entities[$className]);
            unset($this->clones[$className]);
        } else {
            $this->entities = [];
            $this->clones = [];
        }
    }

    /**
     * Cannot use type hints, as this is compatible with Doctrine's change tracker interface
     * @param object $sender The entity whose property has changed
     * @param string $propertyName
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    public function propertyChanged($sender, $propertyName, $oldValue, $newValue)
    {
        $className = ObjectHelper::getObjectClassName($sender);
        if (property_exists($className, $propertyName)) {
            $this->trackedChanges[$className][$propertyName] = $newValue;
        }
    }
    
    /**
     * Generate a string that can be used as an index for the primary key value. For keys which 
     * contain a lot of data (should be rare), use a hash.
     * @param array $pkValues
     * @return string
     */
    private function getIndexForPk(array $pkValues): string
    {
        $pkJson = json_encode(array_values($pkValues));
        $pkIndex = strlen($pkJson) < 40 ? $pkJson : md5($pkJson);

        return $pkIndex;
    }

    private function cloneEntity(object $entity)
    {
        $clone = clone($entity);
        //Clone child objects 1 level deep
        $reflectionClass = new \ReflectionClass($entity);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($entity instanceof EntityProxyInterface && $entity->isChildAsleep($reflectionProperty->getName())) {
                continue;
            }
            $value = ObjectHelper::getValueFromObject($entity, $reflectionProperty->getName());
            if (is_object($value)) {
                ObjectHelper::setValueOnObject($clone, $reflectionProperty->getName(), clone($value));
            } elseif (is_array($value) && $value && is_object(reset($value))) {
                $cloneArray = [];
                foreach ($value as $key => $valueArrayElement) {
                    if (is_object($valueArrayElement)) {
                        $cloneArray[$key] = clone($valueArrayElement);
                    } else {
                        $cloneArray[$key] = $valueArrayElement;
                    }
                }
                ObjectHelper::setValueOnObject($clone, $reflectionProperty->getName(), $cloneArray);
            }
        }

        return $clone;
    }
}
