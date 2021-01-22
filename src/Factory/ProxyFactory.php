<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ProxyFactory
{
    /** @var bool In debug mode, we do include the directory path in error messages, and we don't cache proxies. */
    protected bool $devMode = true;

    /** @var string Directory in which to store files containing proxy class definitions */
    protected string $cacheDirectory;

    /**
     * @var array List of proxy classes this factory has used (if in debug mode, these will be deleted on
     * destruction of the factory.
     */
    protected array $proxyClasses = [];

    /**
     * ProxyFactory constructor.
     * @param bool $devMode
     * @param string|null $cacheDirectory
     * @throws ObjectiphyException
     */
    public function __construct(bool $devMode = true, ?string $cacheDirectory = null)
    {
        $this->devMode = $devMode;
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
                throw new ObjectiphyException('Objectiphy proxy directory does not exist' . ($devMode ? ' (' . $cacheDirectory . ')' : ''));
            } elseif (!is_writable($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy proxy directory is not writable' . ($devMode ? ' (' . $cacheDirectory . ')' : ''));
            } else {
                $this->cacheDirectory = $cacheDirectory;
            }
        } else {
            $this->cacheDirectory = sys_get_temp_dir();
        }

        if ($this->devMode) {
            $this->clearProxyCache();
        }
    }

    /**
     * Create a proxy for an unhydrated entity so that it can be used to specify relationships without having to load
     * the full entity from the database.
     * @param string $className
     * @param array $pkValues Values of primary key (keyed on property name)
     * @param array $constructorArgs
     * @return ObjectReferenceInterface|null
     * @throws \ReflectionException
     */
    public function createObjectReferenceProxy(
        string $className, 
        array $pkValues, 
        array $constructorArgs = []
    ): ?ObjectReferenceInterface {
        $proxyClassName = str_replace('\\', '_', 'ObjReference_' . $className);
        if (!$this->proxyExists($proxyClassName) && !class_exists($proxyClassName)) {
            if (class_exists($className)) {
                $reflectionClass = new \ReflectionClass($className);
                $getterMethod = $this->getReflectionMethod($reflectionClass, '__get');
                $classDefinition = file_get_contents(__DIR__ . '/../Orm/ObjectReference.php');
                $classDefinition = $this->customiseClassDefinition(
                    $classDefinition,
                    $className,
                    $proxyClassName,
                    $getterMethod
                );

                $classDefinition = str_replace('namespace Objectiphy\Objectiphy\Orm;',
                                               'use Objectiphy\Objectiphy\Orm\ObjectHelper;', $classDefinition);
                $classDefinition = str_replace("class ObjectReference implements ObjectReferenceInterface",
                                               "class $proxyClassName extends \\$className implements ObjectReferenceInterface",
                                               $classDefinition);
                $classDefinition = str_replace("public function __toString(): string\n    {",
                                               "public function __toString(): string\n    {if (is_callable('parent::__toString')) {return parent::__toString();}",
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
            $proxy = new $proxyClassName(...$constructorArgs);
            $proxy->setClassDetails($className, $pkValues);

            return $proxy;
        }

        return null;
    }

    /**
     * Create a proxy to intercept property access that needs to be lazy loaded and return its name.
     * @param string $className The entity class to wrap in a proxy for lazy loading.
     * @return string|null Name of proxy class, if one was successfully created.
     * @throws \ReflectionException
     */
    public function createEntityProxy(string $className): ?string
    {
        $proxyClassName = str_replace('\\', '_', 'Objectiphy_Proxy_' . $className);

        if (!class_exists($proxyClassName) && !$this->proxyExists($proxyClassName)) {
            //Build proxy methods using reflection
            $reflectionClass = new \ReflectionClass($className);
            $getterMethod = $this->getReflectionMethod($reflectionClass, '__get');
            $setterMethod = $this->getReflectionMethod($reflectionClass, '__set');
            $issetMethod = $this->getReflectionMethod($reflectionClass, '__isset');
            $classDefinition = file_get_contents(__DIR__ . '/../Orm/EntityProxy.php');
            $classDefinition = $this->customiseClassDefinition(
                $classDefinition,
                $className,
                $proxyClassName,
                $getterMethod,
                $setterMethod,
                $issetMethod
            );
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
    public function clearProxyCache(): void
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
     * If in debug mode, delete proxy class files after use.
     */
    public function __destruct()
    {
        if ($this->devMode && $this->proxyClasses) {
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
        }
    }

    /**
     * @param string $className
     * @param string $proxyClassName
     * @param \ReflectionMethod|null $getterMethod
     * @param \ReflectionMethod|null $setterMethod
     * @param \ReflectionMethod|null $issetMethod
     * @return string
     * @throws \ReflectionException
     */
    private function customiseClassDefinition(
        string $classDefinition,
        string $className,
        string $proxyClassName,
        ?\ReflectionMethod $getterMethod = null,
        ?\ReflectionMethod $setterMethod = null,
        ?\ReflectionMethod $issetMethod = null
    ): string {
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

    /**
     * @param \ReflectionMethod $reflectionMethod
     * @return string
     * @throws \ReflectionException
     */
    private function getMethodDeclaration(\ReflectionMethod $reflectionMethod): string
    {
        $declaration = implode(' ', \Reflection::getModifierNames($reflectionMethod->getModifiers()));
        $declaration .= ' function ';
        $declaration .= $reflectionMethod->getName();
        $declaration .= '(';
        foreach ($reflectionMethod->getParameters() as $parameter) {
            if ($parameter->hasType()) {
                $declaration .= ($parameter->getType() ? ObjectHelper::getTypeName($parameter->getType()) : '') . ' ';
            }
            $declaration .= '$' . $parameter->getName();
            if ($parameter->isOptional()) {
                $declaration .= ' = ';
                $declaration .= $parameter->getDefaultValueConstantName() ?: $parameter->getDefaultValue();
            }
            $declaration .= ',';
        }
        $declaration = rtrim($declaration, ',') . ')';
        $reflectionType = $reflectionMethod->getReturnType();
        $returnType = $reflectionType ? ObjectHelper::getTypeName($reflectionType) : '';
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
    private function proxyExists($className): bool
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
    private function createProxyFromFile($classDefinition, $className): void
    {
        $fileName = $this->cacheDirectory . DIRECTORY_SEPARATOR . str_replace('\\', '_', ltrim($className, '\\')) . '.php';
        file_put_contents($fileName, $classDefinition);
        include_once($fileName);
        $this->proxyClasses[] = $className;
    }
}
