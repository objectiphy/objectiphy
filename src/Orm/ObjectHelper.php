<?php

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * This class only contains static methods. In order to prevent hidden dependencies and brittle code, the only methods
 * allowed here are ones which do not change application state (except for on arguments that are passed into the method
 * for that purpose), and which do not have any dependencies other than arguments that are passed into the method.
 */
class ObjectHelper
{
    public static DataTypeHandlerInterface $dataTypeHandler;

    /**
     * Try various techniques for getting a property value - see if there is a getter, a public property, or use
     * reflection to access a protected or private property. If values cannot be obtained, optionally return a
     * default value.
     * @param object|null $object $object Object whose property we want to read.
     * @param string $propertyName Name of property.
     * @param null $defaultValueIfNotFound Default value to return if we could not obtain the property value.
     * @param bool $lookForGetter Whether or not to look for a getter method.
     * @return mixed|null
     */
    public static function getValueFromObject(
        ?object $object,
        string $propertyName,
        $defaultValueIfNotFound = null,
        bool $lookForGetter = true
    ) {
        $value = $defaultValueIfNotFound;
        $valueFound = false;

        try {
            if ($object) {
                if (!property_exists($object, $propertyName) && $lookForGetter) {
                    $valueFound = self::getValueFromGetter($object, $propertyName, $value);
                }
                if (!$valueFound) {
                    //If lazy loaded, property might exist but be unset, which would cause a reflection error.
                    if ($object instanceof EntityProxyInterface && $object->isChildAsleep($propertyName)) {
                        $object->triggerLazyLoad($propertyName);
                        if (!isset($object->$propertyName)) { //Won't be set if lazy loader didn't load anything
                            $valueFound = true;
                        }
                    } elseif ($object instanceof ObjectReferenceInterface) {
                        if (in_array($propertyName, array_keys($object->getPkValues()))) {
                            $value = $object->getPkValue($propertyName);
                            $valueFound = true;
                        }
                    }
                    if (!$valueFound) { //Try to get from protected or private property
                        $reflectionProperty = new \ReflectionProperty($object, $propertyName);
                        $reflectionProperty->setAccessible(true);
                        if ($reflectionProperty->isInitialized($object) === false) {
                            $value = null;
                        } else {
                            $value = $reflectionProperty->getValue($object);
                        }
                        $valueFound = true;
                    }
                }
            }
        } catch (\Throwable $ex) { }

        try {
            if (!$valueFound && $object && $lookForGetter) {
                self::getValueFromGetter($object, $propertyName, $value);
            }
        } catch (\Throwable $ex) {
            //Don't panic, just use the default value provided
            $value = $defaultValueIfNotFound;
        }
        
        return $value;
    }
    
    /**
     * Set the value of a property on the given entity to the given value.
     * @param $object
     * @param $propertyName
     * @param $valueToSet
     * @param bool $lookForSetter
     * @return bool Whether or not the value was set successfully.
     */
    public static function setValueOnObject(
        $object,
        string $propertyName,
        $valueToSet,
        bool $lookForSetter = true
    ): bool {
        $isValueSet = false;
        if ($valueToSet instanceof \Closure && $object instanceof EntityProxyInterface) {
            $object->setLazyLoader($propertyName, $valueToSet);
            $isValueSet = true;
        } elseif (!property_exists($object, $propertyName) && $lookForSetter) {
            $isValueSet = self::setValueWithSetter($object, $propertyName, $valueToSet);
        }
        if (!$isValueSet) {
            try {
                $reflectionProperty = new \ReflectionProperty($object, $propertyName);
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($object, $valueToSet);
                $isValueSet = true;
            } catch (\Throwable $ex) {
                if ($lookForSetter) {
                    $isValueSet = self::setValueWithSetter($object, $propertyName, $valueToSet);
                }
            }
        }
        
        return $isValueSet;
    }

    /**
     * Copy a value from the property of one object to the property of another object (a shortcut for doing both a
     * get and a set)
     * @param object $sourceObject
     * @param string $sourceProperty
     * @param object $targetObject
     * @param string $targetProperty If empty, the value of $sourceProperty will be used
     */
    public static function populateFromObject(
        object $sourceObject,
        string $sourceProperty,
        object $targetObject,
        string $targetProperty = ''
    ): void {
        $sourceValue = self::getValueFromObject($sourceObject, $sourceProperty);
        self::setValueOnObject($targetObject, $targetProperty ?: $sourceProperty, $sourceValue);
    }
    
    /**
     * Return the class name of an object, taking into account proxies.
     * @param $object
     * @return string
     */
    public static function getObjectClassName(?object $object): string
    {
        $className = '';
        if ($object instanceof EntityProxyInterface || $object instanceof ObjectReferenceInterface) {
            $className = get_parent_class($object);
            if (!$className && $object instanceof ObjectReferenceInterface) {
                $className = $object->getClassName();
            }
        } elseif (is_object($object)) {
            $className = get_class($object);
        }

        return $className;
    }

    public static function getTypeName(\ReflectionType $reflectionType, $defaultToStdClass = true)
    {
        $type = '';
        if (\PHP_MAJOR_VERSION < 8) {
            $type = $reflectionType->getName();
        } elseif ($reflectionType instanceof \ReflectionNamedType) {
            $type = $reflectionType->getName();
        } elseif ($reflectionType instanceof \ReflectionUnionType) {
            $type = reset($reflectionType->getTypes());
        }

        return $type ?: ($defaultToStdClass ? 'stdClass' : '');
    }

    /**
     * Use a getter method to get a value, if possible
     * @param object $object
     * @param string $propertyName
     * @param mixed $value The value to return
     * @return bool Whether or not a getter method was successfully called
     */
    private static function getValueFromGetter(object $object, string $propertyName, &$value): bool
    {
        try {
            if ($object && method_exists($object, 'get' . ucfirst($propertyName))) {
                $className = self::getObjectClassName($object);
                $reflectionMethod = new \ReflectionMethod($className, 'get' . ucfirst($propertyName));
                if ($reflectionMethod->isPublic() && $reflectionMethod->getNumberOfRequiredParameters() == 0) {
                    $value = $object->{'get' . ucfirst($propertyName)}();
                    return true;
                }
            }

        } catch (\Throwable $ex) { }

        return false;
    }

    /**
     * Try to set a value using a setter method
     * @param object $object
     * @param string $propertyName
     * @param $valueToSet
     * @return bool Whether or not the setter method was successfully called
     */
    private static function setValueWithSetter(object $object, string $propertyName, $valueToSet): bool
    {
        try {
            if (method_exists($object, 'set' . ucfirst($propertyName))) {
                $className = self::getObjectClassName($object);
                $reflectionMethod = new \ReflectionMethod($className, 'set' . ucfirst($propertyName));
                if ($reflectionMethod->isPublic()
                    && $reflectionMethod->getNumberOfParameters() >= 1
                    && $reflectionMethod->getNumberOfRequiredParameters() <= 1
                ) {
                    //Check whether type hint compatible with value, if applicable
                    $typeHint = $reflectionMethod->getParameters()[0]->getClass();
                    $typeHintString = $typeHint ? $typeHint->getName() : '';
                    if (!$typeHintString
                        || ($reflectionMethod->getParameters()[0]->isOptional() && $valueToSet === null)
                        || (!$typeHintString && $valueToSet)
                        || ($typeHint && $valueToSet instanceof $typeHintString)
                    ) {
                        $object->{'set' . ucfirst($propertyName)}($valueToSet);
                        return true;
                    }
                }
            }
        } catch (\Throwable $ex) { }
        
        return false;
    }
}
