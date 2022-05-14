<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\EntityProxyInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * A local cache of entities retrieved, and a cloned copy of each for tracking changes.
 */
class EntityTracker
{
    private array $entities = [];
    private array $clones = [];
    private array $trackedChanges = [];

    /**
     * Store an entity in the tracker, and make a shallow clone to track for changes.
     * @param object $entity
     * @param array $pkValues
     */
    public function storeEntity(object $entity, array $pkValues): void
    {
        $entityClass = ObjectHelper::getObjectClassName($entity);
        if (!$pkValues) { //No primary key, but we can still track it
            $pkIndex = $this->hasEntity($entity) ?: ('NO_PK_' . (count($this->entities[$entityClass] ?? []) + 1));
        } else {
            $pkIndex = $this->getIndexForPk($pkValues);
        }

        if (strlen($pkIndex) > 2) {
            $this->entities[$entityClass][$pkIndex] = $entity;
            try {
                $this->clones[$entityClass][$pkIndex] = $this->cloneEntity($entity);
            } catch (\Throwable $ex) {
                //Not the end of the world, we'll just assume it is dirty
                unset($this->clones[$entityClass][$pkIndex]);
            }
        }
    }

    /**
     * Check whether the tracker holds an instance of the given object (either the object itself,
     * or class name with the given primary key values. Return the primary key index if found.
     * @param $entityOrClassName
     * @param array $pkValues
     * @return string|null
     */
    public function hasEntity($entityOrClassName, array $pkValues = []): ?string
    {
        if (is_string($entityOrClassName)) {
            $pkIndex = $this->getIndexForPk($pkValues);
            if (strlen($pkIndex) > 2) {
                return isset($this->entities[$entityOrClassName][$pkIndex]) ? $pkIndex : null;
            }
        } elseif (is_object($entityOrClassName)) {
            $className = ObjectHelper::getObjectClassName($entityOrClassName);
            $pkIndex = $this->getIndexForPk($pkValues);
            if (strlen($pkIndex) > 2) {
                return isset($this->entities[$className][$pkIndex]) ? $pkIndex : null;
            } else {
                $searchResult = array_search($entityOrClassName, $this->entities[$className] ?? [], true);
                return $searchResult ? strval($searchResult) : null;
            }
        }

        return null;
    }

    /**
     * Retrieve an existing entity from the tracker.
     * @param string $className
     * @param array $pkValues
     * @return object|null
     */
    public function getEntity(string $className, array $pkValues): ?object
    {
        $pkIndex = $this->getIndexForPk($pkValues);
        return $this->entities[$className][$pkIndex] ?? null;
    }

    /**
     * Retrieve the clone of an existing entity from the tracker (to see what it was like when it was loaded).
     * @param string $className
     * @param array $pkValues
     * @return object|null
     */
    public function getClone(string $className, array $pkValues): ?object
    {
        $pkIndex = $this->getIndexForPk($pkValues);
        return $this->clones[$className][$pkIndex] ?? null;
    }

    /**
     * @param object $entity
     * @param array $pkValues
     * @return bool Whether or not anything has changed on the given entity (if unknown, returns true)
     * @throws \ReflectionException
     */
    public function isEntityDirty(object $entity, array $pkValues): bool
    {
        $entityClass = ObjectHelper::getObjectClassName($entity);
        if (!$pkValues) { //No primary key, but we can still track it
            $pkIndex = $this->hasEntity($entity) ?: ('NO_PK_' . (count($this->entities[$entityClass] ?? []) + 1));
        } else {
            $pkIndex = $this->getIndexForPk($pkValues);
        }
        if (isset($this->trackedChanges[$entityClass][$pkIndex])) {
            return true;
        } elseif (isset($this->clones[$entityClass])) {
            $clone = $this->clones[$entityClass][$pkIndex] ?? null;
            if ($clone === null && $entity === null) {
                return false;
            } elseif ($clone === null) {
                return true;
            }
            //We have values for both clone and entity, so check if they are the same (skip children as they will be checked separately if required)
            $reflectionProperties = (new \ReflectionClass($entityClass))->getProperties();
            foreach ($reflectionProperties as $reflectionProperty) {
                if ($entity instanceof EntityProxyInterface && $entity->isChildAsleep($reflectionProperty->getName())) {
                    continue;
                }
                $entityValue = ObjectHelper::getValueFromObject($entity, $reflectionProperty->getName());
                if (!(is_object($entityValue) || is_iterable($entityValue) || ($entityValue instanceof \DateTimeInterface))) {
                    $cloneValue = ObjectHelper::getValueFromObject($clone, $reflectionProperty->getName());
                    if ($cloneValue != $entityValue) { //Not strict, eg. for comparing DateTimes
                        return true;
                    }
                }
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * Return list of properties and values that may need updating. If we are tracking the entity,
     * only values that have changed will be returned - otherwise, all properties will be returned.
     * The return values are keyed by property name
     * @param object $entity
     * @param array $pkValues
     * @param array $propertiesToCheck If supplied, filtered to just check the supplied properties, otherwise, check all.
     * @return array
     * @throws \ReflectionException
     */
    public function getDirtyProperties(object $entity, array $pkValues, array $propertiesToCheck = []): array
    {
        $changes = [];
        $className = ObjectHelper::getObjectClassName($entity);
        $pkIndex = $this->getIndexForPk($pkValues);
        if (isset($this->trackedChanges[$className][$pkIndex])) {
            return $this->trackedChanges[$className];
        }
        $clone = $this->clones[$className][$pkIndex] ?? null;
        $reflectionClass = new \ReflectionClass($className);
        $reflectionProperties = $reflectionClass->getProperties();
        while ($reflectionClass = $reflectionClass->getParentClass()) {
            $reflectionProperties = array_merge($reflectionProperties, $reflectionClass->getProperties());
        }
        foreach ($reflectionProperties as $reflectionProperty) {
            $property = $reflectionProperty->getName();
            if (!$propertiesToCheck || in_array($property, $propertiesToCheck)) {
                $entityValue = null;
                if ($this->isPropertyDirty($entity, $property, $entityValue, $clone)) {
                    $changes[$property] = $entityValue;
                }
            }
        }

        return $changes;
    }

    /**
     * Detect which children are missing.
     * @param object $entity
     * @param string $propertyName
     * @param array $childPks
     * @return array|null
     */
    public function getRemovedChildren(object $entity, string $propertyName, array $childPks): ?array
    {
        $removedChildren = [];
        $className = ObjectHelper::getObjectClassName($entity);
        $pkIndex = $this->hasEntity($entity);

        if ($pkIndex) {
            $clone = $this->clones[$className][$pkIndex] ?? null;
            if (!$clone || ($clone instanceof EntityProxyInterface && $clone->isChildAsleep($propertyName))) {
                //We are not tracking changes on this child, so will need to refresh from database.
                return null;
            }
            $clonedCollection = ObjectHelper::getValueFromObject($clone, $propertyName) ?? [];
            $clonedCollection = is_iterable($clonedCollection) ? $clonedCollection : [$clonedCollection];
            $entityCollection = ObjectHelper::getValueFromObject($entity, $propertyName) ?? [];
            $entityCollection = is_iterable($entityCollection) ? $entityCollection : [$entityCollection];
            foreach ($clonedCollection as $clonedChildItem) {
                $pkValueMatch = false;
                foreach ($childPks as $childPk) {
                    $clonePkValue = ObjectHelper::getValueFromObject($clonedChildItem, $childPk);
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

    public function isRelationshipDirty(object $entity, string $propertyPath): bool
    {
        try {
            //Drill down on both entity and clone to check if they are different.
            $className = ObjectHelper::getObjectClassName($entity);
            $pkIndex = array_search($entity, $this->entities[$className] ?? [], true);
            if ($pkIndex) {
                $clone = $this->clones[$className][$pkIndex] ?? null;
                if ($clone) {
                    //Drill down into both
                    while (strpos($propertyPath, '.') !== false) {
                        $nextPart = strtok($propertyPath, '.');
                        if ($entity instanceof EntityProxyInterface && $entity->isChildAsleep($nextPart)) {
                            return false;
                        }
                        if ($clone instanceof EntityProxyInterface && $clone->isChildAsleep($nextPart)) {
                            return true;
                        }
                        $entity = ObjectHelper::getValueFromObject($entity, $nextPart);
                        $clone = ObjectHelper::getValueFromObject($clone, $nextPart);
                        $propertyPath = substr($propertyPath, strlen($nextPart) + 1);
                    }

                    $entityValue = ObjectHelper::getValueFromObject($entity, $propertyPath);
                    $cloneValue = ObjectHelper::getValueFromObject($clone, $propertyPath);

                    //Compare
                    if (is_object($entityValue) && !($entityValue instanceof \DateTimeInterface)) {
                        return true;
                    } else {
                        return $entityValue != $cloneValue;
                    }
                }
            }
        } catch (\Throwable $ex) {
            //assume dirt
        }

        return true;
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
     * Remove a deleted entity from the tracker
     * @param object $entity
     */
    public function removeEntity(object $entity): void
    {
        $key = $this->hasEntity($entity);
        $this->removeByKey($entity, $key);
    }

    /**
     * Remove a deleted entity from the tracker using just primary key values
     * @param string $className
     * @param array $pkValues
     */
    public function remove(string $className, array $pkValues)
    {
        $key = $this->hasEntity($className, $pkValues);
        $this->removeByKey($className, $key);
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

    private function removeByKey($entityOrClass, ?string $key)
    {
        if ($key) {
            $className = is_string($entityOrClass) ? $entityOrClass : ObjectHelper::getObjectClassName($entityOrClass);
            unset($this->entities[$className][$key]);
            unset($this->clones[$className][$key]);
        }
    }

    private function isPropertyDirty(object $entity, string $property, &$entityValue = null, ?object $clone = null): bool
    {
        //If it is a Doctrine proxy that has never woken up, it won't be dirty
        if (property_exists($entity, '__isInitialized__') && $entity->__isInitialized__ === false) {
            return false;
        }
        if (!($entity instanceof EntityProxyInterface) || !$entity->isChildAsleep($property)) { //Shh. Don't wake up the kids.
            $entityValue = ObjectHelper::getValueFromObject($entity, $property);
            if ($clone instanceof EntityProxyInterface && $clone->isChildAsleep($property)) {
                //Yah, it's dirty alright - but no need to wake it up just yet
                return true;
            } else {
                $notFound = '**!VALUE_NOT_FOUND!**';
                $cloneValue = $clone ? ObjectHelper::getValueFromObject(
                    $clone,
                    $property,
                    $notFound,
                    true,
                    true
                ) : $notFound;
                if ($cloneValue == $notFound) {
                    return true;
                } elseif (is_scalar($entityValue) || $entityValue instanceof \DateTimeInterface) {
                    return $entityValue != $cloneValue;
                } else {
                    return $entityValue !== $cloneValue;
                }
            }
        }

        return false;
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
        return strlen($pkJson) < 40 ? $pkJson : md5($pkJson);
    }

    /**
     * @param object $entity
     * @return object
     * @throws \ReflectionException
     */
    private function cloneEntity(object $entity): object
    {
        $clone = $this->getOrCreateClone($entity);
        //Clone child objects 1 level deep
        $reflectionClass = new \ReflectionClass(ObjectHelper::getObjectClassName($entity));
        //$reflectionClass = new \ReflectionClass($entity);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($entity instanceof EntityProxyInterface && $entity->isChildAsleep($reflectionProperty->getName())) {
                //We need to know it was asleep so we can detect removals by loading from the database
                $clone->setLazyLoader($reflectionProperty->getName(), fn() => null);
                continue;
            }
            $value = ObjectHelper::getValueFromObject($entity, $reflectionProperty->getName());
            if (is_object($value)) {
                ObjectHelper::setValueOnObject($clone, $reflectionProperty->getName(), $this->getOrCreateClone($value));
            } elseif (is_array($value) && $value && is_object(reset($value))) {
                $cloneArray = [];
                foreach ($value as $key => $valueArrayElement) {
                    if (is_object($valueArrayElement)) {
                        $cloneArray[$key] = $this->getOrCreateClone($valueArrayElement);
                    } else {
                        $cloneArray[$key] = $valueArrayElement;
                    }
                }
                ObjectHelper::setValueOnObject($clone, $reflectionProperty->getName(), $cloneArray);
            }
        }

        return $clone;
    }

    private function getOrCreateClone(object $entity)
    {
        //If we already have a clone of this, use it, otherwise create a new one
        $className = ObjectHelper::getObjectClassName($entity);
        $key = array_search($entity, $this->entities[$className] ?? [], true);
        if ($key) {
            $clone = $this->clones[$className][$key] ?? clone($entity);
        } else {
            $clone = clone($entity);
        }

        return $clone;
    }
}
