<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Contract\EntityProxyInterface;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ProxyFactory
{
    /** @var bool In production mode, we do not include the directory path in error messages, and we cache proxies. */
    protected bool $productionMode = false;

    /** @var string Directory in which to store files containing proxy class definitions */
    protected string $cacheDirectory;

    /**
     * @var array List of proxy classes this factory has used (if not in production mode, these will be deleted on
     * destruction of the factory.
     */
    protected array $proxyClasses = [];

    public function __construct(bool $productionMode = false, ?string $cacheDirectory = null)
    {
        $this->productionMode = $productionMode;
        if ($cacheDirectory) {
            if (!file_exists($cacheDirectory) && substr($cacheDirectory, -10) == 'objectiphy' && strlen($cacheDirectory) > 12) {
                //Won't do any harm to try and create this directory
                try {
                    @mkdir($cacheDirectory, 0755, true);
                } catch (\Throwable $ex) {
                    //Fall through
                }
            }
            if (!file_exists($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy proxy directory does not exist' . ($productionMode ? '' : ' (' . $cacheDirectory . ')'));
            } elseif (!is_writable($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy proxy directory is not writable' . ($productionMode ? '' : ' (' . $cacheDirectory . ')'));
            } else {
                $this->cacheDirectory = $cacheDirectory;
            }
        } else {
            $this->cacheDirectory = sys_get_temp_dir();
        }

        if (!$this->productionMode) {
            $this->clearProxyCache();
        }
    }

    /**
     * Create a proxy for an unhydrated entity so that it can be used to specify relationships without having to load
     * the full entity from the database.
     * @param string $className
     * @param mixed $id Value of primary key (can be an array if the key is composite)
     * @param string|array $primaryKeyProperty Name of primary key property (can be an array if the key is composite)
     * @param array $constructorArgs
     * @return null
     */
    public function createObjectReferenceProxy(string $className, $id, $primaryKeyProperty, array $constructorArgs = [])
    {
        $proxyClassName = str_replace('\\', '_', 'Objectiphy_ObjectReference_' . $className);
        if (!$this->proxyExists($proxyClassName) && !class_exists($proxyClassName)) {
            if (class_exists($className)) {
                $classDefinition = file_get_contents(__DIR__ . '/ObjectReference.php');
                $classDefinition = str_replace('namespace Objectiphy\Objectiphy;',
                                               'use Objectiphy\ObjectHelper;', $classDefinition);
                $classDefinition = str_replace("class ObjectReference implements ObjectReferenceInterface",
                                               "class $proxyClassName extends \\$className implements "
                                               . ObjectReferenceInterface::class,
                                               $classDefinition);

                //Remove the constructor (we don't want to override the real object's constructor)
                $constructorStart = strpos($classDefinition, 'public function __construct(');
                $constructorEnd = strpos($classDefinition, '}', $constructorStart);
                $classDefinition = substr($classDefinition, 0, $constructorStart)
                                    . substr($classDefinition, $constructorEnd + 1);

                $this->createProxyFromFile($classDefinition, $proxyClassName);
            }
        }

        if (class_exists($proxyClassName)) {
            /** @var ObjectReferenceInterface $proxy */
            $proxy = new $proxyClassName(...$constructorArgs);
            $proxy->setClassDetails($className, $id, $primaryKeyProperty);

            return $proxy;
        }

        return null;
    }

    /**
     * Create a proxy to intercept property access that needs to be lazy loaded.
     * @param object|string $entityOrClassName The entity to wrap in a proxy for lazy loading.
     * @return string|null Name of proxy class, if one was successfully created.
     * @throws \ReflectionException
     */
    public function createEntityProxy($className): ?string
    {
        $proxyClassName = str_replace('\\', '_', 'Objectiphy_Proxy_' . $className);

        if (!class_exists($proxyClassName) && !$this->proxyExists($proxyClassName)) {
            //Buid proxy methods using reflection
//            $proxyMethods = [];
//            $getterArg = 'objectiphyGetPropertyName';
//            $getByRef = true;
//            $setterArg1 = 'objectiphySetPropertyName';
//            $setterArg2 = 'objectiphySetValue';
//            $issetArg = 'objectiphyIsSetPropertyName';
            $reflectionClass = new \ReflectionClass($className);
            $getterMethod = $this->getReflectionMethod($reflectionClass, '__get');
            $setterMethod = $this->getReflectionMethod($reflectionClass, '__set');
            $issetMethod = $this->getReflectionMethod($reflectionClass, '__isset');
            $classDefinition = $this->customiseClassDefinition(
                $className,
                $proxyClassName,
                $getterMethod,
                $setterMethod,
                $issetMethod
            );

//            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
//                if ($reflectionMethod->getName() == '__get') {
//                    $getterMethod = $reflectionMethod;
//                    //Make sure our method is compatible with the entity
//                    $getterArgs = $reflectionMethod->getParameters();
//                    $getterArg = $getterArgs[0]->getName();
//                    $getByRef = $reflectionMethod->returnsReference();
//                    $getterReturnType = $reflectionMethod->getReturnType()->getName();
//                } elseif ($reflectionMethod->getName() == '__set') {
//                    $setterMethod = $reflectionMethod;
//                    $setterArgs = $reflectionMethod->getParameters();
//                    $setterArg1 = $setterArgs[0]->getName();
//                    $setterArg2 = $setterArgs[1]->getName();
//                    $setterReturnType = $reflectionMethod->getReturnType()->getName();
//                } elseif ($reflectionMethod->getName() == '__isset') {
//                    $issetArgs = $reflectionMethod->getParameters();
//                    $issetArg = $issetArgs[0]->getName();
//                    $issetReturnType = $reflectionMethod->getReturnType()->getName();
//                }
//            }
//
//            $classDefinition = file_get_contents(__DIR__ . '/EntityProxy.php');
//
//            if (!$getByRef) {
//                $classDefinition = str_replace('public function &__get', 'public function __get', $classDefinition);
//            }
//            $classDefinition = str_replace('objectiphyGetPropertyName', $getterArg, $classDefinition);
//            $classDefinition = str_replace('objectiphySetPropertyName', $setterArg1, $classDefinition);
//            $classDefinition = str_replace('objectiphyIsSetPropertyName', $issetArg, $classDefinition);
//            $classDefinition = str_replace('objectiphySetValue', $setterArg2, $classDefinition);
//            $classDefinition = str_replace('namespace Objectiphy\\Objectiphy;', 'use Objectiphy\\Objectiphy\\ObjectHelper;', $classDefinition);
//            $classDefinition = str_replace('class EntityProxy implements EntityProxyInterface',
//                                           "class $proxyClassName extends \\" . $reflectionClass->getName() . " implements " . EntityProxyInterface::class,
//                                           $classDefinition);
//            $classDefinition = str_replace('/**************************************************
//     ***    Auto-generated proxy methods go here.   ***
//     **************************************************/', implode("\n\n    ", $proxyMethods), $classDefinition);
            $this->createProxyFromFile($classDefinition, $proxyClassName);
        }

        if (class_exists($proxyClassName)) {
            return $proxyClassName;
        }

        return null;
    }

    /**
     * Delete files for proxy classes so they will be regenerated next time.
     */
    public function clearProxyCache()
    {
        if (file_exists($this->cacheDirectory)) {
            $proxies = array_diff(scandir($this->cacheDirectory), ['.', '..']);
            foreach ($proxies as $proxy) {
                if (substr($proxy, 0, 11) == 'Objectiphy_') {
                    unlink($this->cacheDirectory . DIRECTORY_SEPARATOR . $proxy);
                }
            }
            clearstatcache();
        }
    }

    /**
     * If not in production mode, delete proxy class files after use.
     */
    public function __destruct()
    {
        if (!$this->productionMode && $this->proxyClasses) {
            foreach ($this->proxyClasses as $proxyClass) {
                if (file_exists($this->cacheDirectory . DIRECTORY_SEPARATOR . $proxyClass . '.php')) {
                    unlink ($this->cacheDirectory . DIRECTORY_SEPARATOR . $proxyClass . '.php');
                }
            }
            clearstatcache();
        }
    }

    private function getReflectionMethod(\ReflectionClass $reflectionClass, string $methodName): ?\ReflectionMethod
    {
        try {
            return $reflectionClass->getMethod($methodName);
        } catch (\Throwable $ex) {
            return null;
        };
    }

    private function customiseClassDefinition(
        string $className,
        string $proxyClassName,
        ?\ReflectionMethod $getterMethod,
        ?\ReflectionMethod $setterMethod,
        ?\ReflectionMethod $issetMethod
    ) {
        $classDefinition = file_get_contents(__DIR__ . '/../Orm/EntityProxy.php');

        $defaultGetter = $getterDeclaration = 'public function &__get(string $objectiphyGetPropertyName)';
        $defaultSetter = $setterDeclaration = 'public function __set(string $objectiphySetPropertyName, $objectiphySetValue): void';
        $defaultIsset = $issetDeclaration = 'public function __isset(string $objectiphyIsSetPropertyName): bool';
        $defaultGetterArg = $getterArg = 'objectiphyGetPropertyName';
        $defaultSetterArg1 = $setterArg1 = 'objectiphySetPropertyName';
        $defaultSetterArg2 = $setterArg2 = 'objectiphySetValue';
        $defaultIssetArg = $issetArg = 'objectiphyIsSetPropertyName';
        if ($getterMethod) {
            $getterArg = $getterMethod->getParameters()[0]->getName() ?? $getterArg;
            $getterDeclaration = $this->getMethodDeclaration($getterMethod);
        }
        if ($setterMethod) {
            $setterArgs = $setterMethod->getParameters();
            $setterArg1 = $setterArgs[0]->getName() ?? $setterArg1;
            $setterArg2 = $setterArgs[1]->getName() ?? $setterArg2;
            $setterDeclaration = $this->getMethodDeclaration($setterMethod);
        }
        if ($issetMethod) {
            $issetArg = $issetMethod->getParameters()[0]->getName() ?? $issetArg;
            $issetDeclaration = $this->getMethodDeclaration($issetMethod);
        }

        $defaults = [
            $defaultGetter,
            $defaultSetter,
            $defaultIsset,
            $defaultGetterArg,
            $defaultSetterArg1,
            $defaultSetterArg2,
            $defaultIssetArg,
        ];
        $replacements = [
            $getterDeclaration,
            $setterDeclaration,
            $issetDeclaration,
            $getterArg,
            $setterArg1,
            $setterArg2,
            $issetArg,
        ];
        $classDefinition = str_replace($defaults, $replacements, $classDefinition);

        $namespace = 'namespace Objectiphy\\Objectiphy\\Orm;';
        $useStatement = 'use Objectiphy\\Objectiphy\\Orm\\ObjectHelper;';
        $classDefinition = str_replace($namespace, $useStatement, $classDefinition);

        $classDeclaration = 'class EntityProxy implements EntityProxyInterface';
        $newClassDeclaration = "class $proxyClassName extends \\" . $className . " implements " . EntityProxyInterface::class;
        $classDefinition = str_replace($classDeclaration, $newClassDeclaration, $classDefinition);

        return $classDefinition;
    }

    private function getMethodDeclaration(\ReflectionMethod $reflectionMethod)
    {
        $declaration = implode(' ', \Reflection::getModifierNames($reflectionMethod->getModifiers()));
        $declaration .= ' function ';
        $declaration .= $reflectionMethod->getName();
        $declaration .= '(';
        foreach ($reflectionMethod->getParameters() as $parameter) {
            if ($parameter->hasType()) {
                $declaration .= ($parameter->getType() ? $parameter->getType()->getName() : '') . ' ';
            }
            $declaration .= '$' . $parameter->getName();
            if ($parameter->isOptional()) {
                $declaration .= ' = ';
                $declaration .= $parameter->getDefaultValueConstantName() ?: $parameter->getDefaultValue();
            }
            $declaration .= ',';
        }
        $declaration = rtrim($declaration, ',') . ')';
        $returnType = $reflectionMethod->getReturnType() ? $reflectionMethod->getReturnType()->getName() : '';
        if ($returnType) {
            $declaration .= ': ' . $returnType;
        }

        return trim($declaration);
    }

    /**
     * Check whether a proxy class already exists.
     * @param $className
     * @return bool
     */
    private function proxyExists($className)
    {
        $fileName = $this->cacheDirectory . DIRECTORY_SEPARATOR . str_replace('\\', '/', $className) . '.php';
        if (file_exists($fileName)) {
            try {
                include_once($fileName);
                $this->proxyClasses[] = $className;

                return true;
            } catch (\Throwable $ex) {
                //Class definition has probably changed - create a new proxy
            }
        }

        return false;
    }

    /**
     * Create a proxy class based on a previously saved class definition.
     * @param $classDefinition
     * @param $className
     */
    private function createProxyFromFile($classDefinition, $className)
    {
        $fileName = $this->cacheDirectory . DIRECTORY_SEPARATOR . str_replace('\\', '_', ltrim($className, '\\')) . '.php';
        file_put_contents($fileName, $classDefinition);
        include_once($fileName);
        $this->proxyClasses[] = $className;
    }
}
