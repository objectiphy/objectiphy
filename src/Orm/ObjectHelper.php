<?php

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;

/**
 * This class only contains static methods. In order to prevent hidden dependencies and brittle code, the only methods
 * allowed here are ones which do not change application state (except for on arguments that are passed into the method
 * for that purpose), and which do not have any dependencies other than arguments that are passed into the method.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class ObjectHelper
{
    public static DataTypeHandlerInterface $dataTypeHandler;

    /**
     * Try various techniques for getting a property value - see if there is a getter, a public property, or use
     * reflection to access a protected or private property. If values cannot be obtained, optionally return a
     * default value.
     * @param object $object Object whose property we want to read.
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

        try {
            if ($object/* && property_exists($object, $propertyName)*/) {
                //If lazy loaded, property might exist but be unset, which would cause a reflection error.
                if ($object instanceof EntityProxyInterface && $object->isChildAsleep($propertyName)) {
                    $object->triggerLazyLoad($propertyName);
                    if (!isset($object->$propertyName)) { //Won't be set if lazy loader didn't load anything
                        return $value;
                    }
                } elseif ($object instanceof ObjectReferenceInterface) {
                    if (in_array($propertyName, array_keys($object->getPkValues()))) {
                        return $object->getPkValue($propertyName);
                    }
                }
                $reflectionProperty = new \ReflectionProperty($object, $propertyName);
                $reflectionProperty->setAccessible(true);
                if ($reflectionProperty->isInitialized($object) === false) {
                    $value = null;
                } else {
                    $value = $reflectionProperty->getValue($object);
                }
            } elseif ($object && $lookForGetter && method_exists($object, 'get' . ucfirst($propertyName))) {
                $reflectionMethod = new \ReflectionMethod(ObjectHelper::getObjectClassName($object), 'get' . ucfirst($propertyName));
                if ($reflectionMethod->isPublic() && $reflectionMethod->getNumberOfRequiredParameters() == 0) {
                    $value = $object->{'get' . ucfirst($propertyName)}();
                }
            }
        } catch (\Throwable $ex) {
            //Don't panic, just use the default value provided
            $value = $defaultValueIfNotFound;
        }

        return $value;
    }

    /**
     * Set the value of a property on the given entity to the given value of the given data type, optionally formatted
     * according to the given format string (format string comes from the mapping definition).
     * @param $object
     * @param $propertyName
     * @param $value
     * @param $dataType
     * @param string $format
     * @param bool $lookForSetter
     * @throws \ReflectionException
     * @throws \Exception
     */
    public static function setValueOnObject(
        $object,
        string $propertyName,
        $valueToSet
    ): void {
        if (array_key_exists('valueToSet', get_defined_vars())) { //isset no good here, as $valueToSet can be null
            if ($valueToSet instanceof \Closure && $object instanceof EntityProxyInterface) {
                $object->setLazyLoader($propertyName, $valueToSet);
            } else {
                try {
                    $reflectionProperty = new \ReflectionProperty($object, $propertyName);
                    $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($object, $valueToSet);
                } catch (\Throwable $ex) {
                    if (method_exists($object, 'set' . ucfirst($propertyName))) {
                        $className = ObjectHelper::getObjectClassName($object);
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
                                || (!$typeHintString && $value)
                                || ($typeHint && $value instanceof $typeHintString)
                            ) {
                                $object->{'set' . ucfirst($propertyName)}($valueToSet);
                            }

                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * Copy a value from the property of one object to the property of another object (a shortcut for doing both a
     * get and a set)
     * @param object $sourceObject
     * @param string $sourceProperty
     * @param object $targetObject
     * @param string $targetProperty If empty, the value of $sourceProperty will be used
     */
    public static function populateFromObject(object $sourceObject, string $sourceProperty, object $targetObject, string $targetProperty = '')
    {
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
}
