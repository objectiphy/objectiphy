<?php

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Exception\MappingException;

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
                        if ($object instanceof EntityProxyInterface) {
                            $value = $object->getPrivatePropertyValue($propertyName, $valueFound);
                        }
                        if (!$valueFound) {
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
                if (!$isValueSet && $object instanceof EntityProxyInterface) {
                    //See if we can set private property on base class
                    $isValueSet = $object->setPrivatePropertyValue($propertyName, $valueToSet);
                }
                if (!$isValueSet && $ex instanceof \TypeError) {
                    $typeError = sprintf('Please specify the type attribute in the mapping definition for property %1$s of class %2$s. %3$s', $propertyName, self::getObjectClassName($object), $ex->getMessage());
                    throw new MappingException($typeError);
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

    public static function getTypeName(\ReflectionType $reflectionType, string $className = '', string $propertyName = '', $defaultToStdClass = false)
    {
        $type = '';
        if ($reflectionType instanceof \ReflectionNamedType) {
            $type = self::sanitizeType($reflectionType->getName());
        } elseif ($reflectionType instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($reflectionType->getTypes() as $reflectionType) {
                $types[] = self::sanitizeType($reflectionType->getName());
            }
            $type = implode(' | ', array_filter($types));
        }
        if (!$type && $className && $propertyName) {
            $type = self::getTypeHacky($className, $propertyName);
        }

        return $type ?: ($defaultToStdClass ? 'stdClass' : '');
    }

    private static function getTypeHacky($className, $propertyName): string
    {
        //PHP ReflectionType seems buggy at times - try a hacky way of checking the type
        try {
            $hackyClass = new $className();
            $value = new \stdClass();
            self::setValueOnObject($hackyClass, $propertyName, $value);
        } catch (\Throwable $ex) {
            $messageType = self::extractPrimitiveTypeFromError($ex->getMessage());
            if ($messageType) {
                return $messageType;
            }
            $classStart = strpos($ex->getMessage(), 'must be an instance of ');
            if ($classStart !== false) {
                $classEnd = strpos($ex->getMessage(), ' or ') ?: (strpos($ex->getMessage(), ',') ?: strlen($ex->getMessage()));
                $length = $classEnd - ($classStart + 23);
                $className = substr($ex->getMessage(), $classStart + 23, $length);
                if (class_exists($className)) {
                    return $className;
                } elseif (class_exists('\\' . $className)) {
                    return '\\' . $className;
                }
            }
            $errorMessage = 'Could not determine data type for %1$s. If this is a collection of child entities, please try adding a collectionClass attribute to the Relationship mapping for this property. Otherwise, please try specifying a the dataType attribute for the column.';
            throw new MappingException(sprintf($errorMessage, $className . '::' . $propertyName));
        }

        return '';
    }

    private static function extractPrimitiveTypeFromError(string $errorMessage)
    {
        $needles = ['string', 'int', 'bool', 'float', 'array', 'object', 'callable', 'iterable'];
        foreach ($needles as $needle) {
            if (strpos($errorMessage, 'must be ' . $needle) !== false) {
                return $needle;
            }
        }
    }

    private static function sanitizeType(string $type): string
    {
        $lowerType = strtolower(str_replace('\\', '', $type));
        switch ($lowerType) {
            case 'datetime':
            case 'datetimeimmutable':
            case 'date':
            case 'date_time':
            case 'datetimestring':
            case 'date_time_string':
            case 'datestring':
            case 'date_string':
            case 'int':
            case 'integer':
            case 'bool':
            case 'boolean':
            case 'float':
            case 'decimal':
            case 'real':
            case 'string':
            case 'null':
            case 'array':
                return $type;
            default:
                if (class_exists($type)) {
                    return $type;
                }
                break;
        }

        return '';
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
                    $reflectionParam = $reflectionMethod->getParameters()[0];
                    $typeHintString = $reflectionParam->getType() && !$reflectionParam->getType()->isBuiltin()
                       ? new ReflectionClass($param->getType()->getName())
                       : null;
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
