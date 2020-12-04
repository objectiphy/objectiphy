<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\PropertyChangedListenerInterface;

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
                $this->clones[$entityClass][$pkIndex] = clone($entity);
            } catch (\Throwable $ex) {
                //Not the end of the world, we'll just assume it is dirty
            }
        }
    }

    /**
     * Check whether the tracker holds an instance of the given class with the given primary key values.
     */
    public function hasEntity(string $className, array $pkValues): bool
    {
        $pkIndex = $this->getIndexForPk($pkValues);
        if (strlen($pkIndex) > 2) {
            return isset($this->entities[$className][$pkIndex]);
        } else {
            return false;
        }
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
        if (isset($this->clones[$className][$pkIndex])) {
            $clone = $this->clones[$className][$pkIndex];
            $reflectionClass = new \ReflectionClass($className);
            foreach ($reflectionClass->getProperties() as $reflectionProperty) {

                $property = $reflectionProperty->getName();
                $entityValue = ObjectHelper::getValueFromObject($entity, $property);
                if (!is_object($entityValue) || $entityValue instanceof \DateTimeInterface) {
                    $cloneValue = ObjectHelper::getValueFromObject($clone, $property, '**!VALUE_NOT_FOUND!**');
                    if ($entityValue != $cloneValue) {
                        $changes[$property] = $entityValue;
                    }
                }
            }
        }

        return $changes;
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
}
