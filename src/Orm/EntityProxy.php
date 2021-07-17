<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Exception\MappingException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * This is a template file for creating proxy object classes. This class will not be instantiated directly.
 */
class EntityProxy implements EntityProxyInterface
{
    /**
     * @var \Closure[] The closure to execute to get the data, keyed on property name.
     */
    protected array $lazyLoaders = [];
    protected array $objectiphySettingValue = [];

    /**
     * If a property is lazy loaded, the closure to populate the value should be specified here.
     * @param string $propertyName
     * @param \Closure $closure
     */
    public function setLazyLoader(string $propertyName, \Closure $closure): void
    {
        $this->lazyLoaders[$propertyName] = $closure;
        unset($this->$propertyName); //Force the magic getter to be used
    }

    /**
     * If a property is ready to be lazy loaded but has not yet actually been loaded, it is asleep.
     * @param string $propertyName
     * @return bool
     */
    public function isChildAsleep(string $propertyName): bool
    {
        return isset($this->lazyLoaders[$propertyName]);
    }

    public function getClassName(): string
    {
        return parent::class; //When in use, it will have a parent
    }

    /**
     * Magic getter to intercept property access and perform lazy loading if necessary.
     * @param string $objectiphyGetPropertyName
     * @return mixed
     * @throws \ReflectionException
     */
    //It is important that the $objectiphyGetPropertyName argument is not changed (proxy factory will replace it)
    public function &__get(string $objectiphyGetPropertyName)
    {
        $this->triggerLazyLoad($objectiphyGetPropertyName);

        $value = null;
        if (property_exists($this, $objectiphyGetPropertyName) && isset($this->$objectiphyGetPropertyName)) {
            $value =& $this->$objectiphyGetPropertyName;
        } elseif (is_callable('parent::__get')) {
            $reflectionMethod = (new \ReflectionClass($this))->getParentClass()->getMethod('__get');
            if ($reflectionMethod->returnsReference()) {
                $value =& parent::__get($objectiphyGetPropertyName);
            } else {
                //Cannot return by reference, but that's your own fault, loser.
                $value = parent::__get($objectiphyGetPropertyName);
            }
        } elseif (method_exists($this, 'get' . ucfirst($objectiphyGetPropertyName))) {
            //Need to return by reference, so don't use the ObjectHelper class here
            $reflectionMethod = new \ReflectionMethod($this, 'get' . ucfirst($objectiphyGetPropertyName));
            if ($reflectionMethod->getNumberOfRequiredParameters() == 0 && $reflectionMethod->returnsReference()) {
                $value =& $this->{'get' . ucfirst($objectiphyGetPropertyName)}();
            } elseif ($reflectionMethod->getNumberOfRequiredParameters() == 0) {
                //Cannot return by reference, but that's still your own fault, and you are still a loser.
                $value = $this->{'get' . ucfirst($objectiphyGetPropertyName)}();
            }
        }

        return $value;
    }

    /**
     * Magic setter to remove lazy loader if a property is set directly without being read.
     * @param string $objectiphySetPropertyName
     * @param $objectiphySetValue
     * @throws \ReflectionException
     */
    //It is important that the $objectiphySetPropertyName and $objectiphySetValue arguments are not changed (proxy
    //factory will replace them)
    public function __set(string $objectiphySetPropertyName, $objectiphySetValue): void
    {
        if (isset($this->lazyLoaders[$objectiphySetPropertyName])) {
            unset($this->lazyLoaders[$objectiphySetPropertyName]);
        }
        $this->setValueObjectiphy($objectiphySetPropertyName, $objectiphySetValue);
    }

    /**
     * Magic method to check whether a property holds a value (will lazy load if necessary)
     * @param string $objectiphyIsSetPropertyName
     * @return bool
     * @throws \ReflectionException
     */
    //It is important that the $objectiphyIsSetPropertyName argument is not changed (proxy factory will replace it)
    public function __isset(string $objectiphyIsSetPropertyName): bool
    {
        $lazyLoaderAvailable = empty($this->objectiphySettingValue[$objectiphyIsSetPropertyName]) 
            ? isset($this->lazyLoaders[$objectiphyIsSetPropertyName]) 
            : false;

        if (!isset($thisObject->$objectiphyIsSetPropertyName) && $lazyLoaderAvailable) {
            //We have to lazy load to see if there are any records
            $this->triggerLazyLoad($objectiphyIsSetPropertyName);
        }

        return isset($this->$objectiphyIsSetPropertyName);
    }

    /**
     * Lazy loads the given property.
     * @param $propertyName
     * @throws \ReflectionException
     */
    public function triggerLazyLoad($propertyName): void
    {
        if (count($this->lazyLoaders)) {
            if (array_key_exists($propertyName, $this->lazyLoaders)) {
                if (!empty($this->lazyLoaders[$propertyName])) {
                    $closure = $this->lazyLoaders[$propertyName];
                    $value = $closure();
                    $this->setValueObjectiphy($propertyName, $value);
                    unset($this->lazyLoaders[$propertyName]);
                }
            }
        }
    }

    public function setPrivatePropertyValue(string $propertyName, $value): bool
    {
        try {
            $reflectionClass = new \ReflectionClass($this);
            $parentReflectionClass = $reflectionClass->getParentClass();
            $reflectionProperty = $parentReflectionClass->getProperty($propertyName);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($this, $value);
            
            return true;
        } catch (\Throwable $ex) {
            if ($ex instanceof \TypeError) {
                $typeError = sprintf('Please specify the type attribute in the mapping definition for property %1$s of class %2$s. %3$s', $propertyName, ObjectHelper::getObjectClassName($this), $ex->getMessage());
                throw new MappingException($typeError);
            }

            return false;
        }
    }
    
    public function getPrivatePropertyValue(string $propertyName, bool &$wasFound)
    {
        try {
            $reflectionClass = new \ReflectionClass($this);
            $parentReflectionClass = $reflectionClass->getParentClass();
            $reflectionProperty = $parentReflectionClass->getProperty($propertyName);
            $reflectionProperty->setAccessible(true);
            $value = $reflectionProperty->getValue($this);
            $wasFound = true;

            return $value;
        } catch (\Throwable $ex) {
            if ($ex instanceof \TypeError) {
                $typeError = sprintf('Please specify the type attribute in the mapping definition for property %1$s of class %2$s: %3$s', $propertyName, ObjectHelper::getObjectClassName($this), $ex->getMessage());
                throw new MappingException($typeError);
            }
            $wasFound = false;

            return null;
        }
    }

    /**
     * @param string $propertyName
     * @param $value
     */
    protected function setValueObjectiphy(string $propertyName, $value): void
    {
        $this->objectiphySettingValue[$propertyName] = true; //So that __isset knows not to count on the lazy loader
        ObjectHelper::setValueOnObject($this, $propertyName, $value);
        
        unset($this->objectiphySettingValue[$propertyName]);
    }
    /** end of proxy **/
}
