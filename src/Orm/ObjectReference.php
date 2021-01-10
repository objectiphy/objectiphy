<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;

/**
 * @author Russell Walker <russell.walker@marmalade.co.uk>
 * Represents a child object which might or might not yet have an ID.
 */
class ObjectReference implements ObjectReferenceInterface
{
    private ?object $object = null;
    private string $className;
    private array $pkValues;

    /**
     * This object represents an instance of an entity (to use either as a placeholder before the object has been
     * persisted, so that the foreign key can be populated after persistence, or as a way of representing a child
     * object without having to actually hydrate the child object (where you just want to store a new association, but
     * are not updating the child entity).
     * @param string|object $classNameOrObject Name of the class this reference represents, or the actual object. If the class
     * name is supplied, the primary key value (ID) should also be supplied.
     * @param array $pkValues Primary key values, keyed by property name, if applicable.
     */
    public function __construct($classNameOrObject, array $pkValues = [])
    {
        $this->setClassDetails($classNameOrObject, $pkValues);
    }

    /**
     * @param string $objectiphyGetPropertyName
     * @return mixed
     */
    public function &__get(string $objectiphyGetPropertyName)
    {
        $propertyValue = null;
        if (array_key_exists($objectiphyGetPropertyName, $this->pkValues)) {
            $propertyValue = $this->getPkValue($objectiphyGetPropertyName);
        }

        return $propertyValue;
    }

    /**
     * When creating an object reference, we will be extending any old entity, which might have its own constructor
     * arguments. In that case, we have to call this method separately.
     * @param string|object $classNameOrObject
     * @param array $pkValues
     */
    public function setClassDetails($classNameOrObject, array $pkValues = []): void
    {
        if (is_string($classNameOrObject)) {
            $this->className = $classNameOrObject;
        } else {
            $this->object = $classNameOrObject;
        }

        $this->pkValues = $pkValues;
        foreach (array_keys($this->pkValues) as $property) {
            if (isset($this->$property)) {
                unset($this->$property); //Forces use of the getter
            }
        }
    }

    /**
     * @return array
     */
    public function getPkValues(): array
    {
        return $this->pkValues;
    }

    /**
     * Get the specified primary key value - prioritise trying to get it from the actual object, if possible, otherwise
     * use the local value.
     * @param string $propertyName
     * @return mixed The value of the primary key property.
     */
    public function getPkValue(string $propertyName)
    {
        $propertyValue = null;
        if (isset($this->pkValues[$propertyName])) {
            $localValue = $this->pkValues[$propertyName];
            $propertyValue = ObjectHelper::getValueFromObject($this->object, $propertyName, $localValue);
        }
        
        return $propertyValue;
    }

    /**
     * Set the value of a primary key property
     * @param string $propertyName
     * @param $value
     */
    public function setPrimaryKeyValue(string $propertyName, $value): void
    {
        if (!empty($this->object) && array_key_exists($propertyName, $this->pkValues)) {
            ObjectHelper::setValueOnObject($this->object, $propertyName, $value);
        } else {
            $this->pkValues[$propertyName] = $value;
        }
    }

    /**
     * @return string The name of the class represented by this reference.
     */
    public function getClassName(): string
    {
        if (!empty($this->className)) {
            return $this->className;
        }

        if (!empty($this->object)) {
            return ObjectHelper::getObjectClassName($this->object);
        }
    }

    /**
     * @return object The object represented by this reference, if applicable.
     */
    public function getObject(): ?object
    {
        return $this->object;
    }

    /**
     * @return string Generated hash to uniquely identify the object.
     */
    public function getObjectHash(): string
    {
        return spl_object_hash($this->getObject() ?? new stdClass());
    }

    /**
     * @return string Either the primary key value as a string index, if known, or the object hash.
     */
    public function __toString(): string
    {
        $pkIndex = null;
        if (!empty($this->pkValues)) {
            $pkJson = json_encode(array_values($this->pkValues));
            $pkIndex = strlen($pkJson) < 40 ? $pkJson : md5($pkJson);
        } 
        if (!$pkIndex) {
            $pkIndex = $this->getObjectHash();
        }
        
        return $pkIndex;
    }
}
