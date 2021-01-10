<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class EntityFactory implements EntityFactoryInterface
{
    private ProxyFactory $proxyFactory;
    private array $entityFactories = [];

    public function __construct(ProxyFactory $proxyFactory)
    {
        $this->proxyFactory = $proxyFactory;
    }

    public function registerCustomEntityFactory(string $className, EntityFactoryInterface $entityFactory): void
    {
        $this->entityFactories[$className] = $entityFactory;
    }

    /**
     * @param string $className
     * @param bool $requiresProxy
     * @return object
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function createEntity(string $className, bool $requiresProxy = false): object
    {
        $entity = null;
        if ($requiresProxy) {
            $proxyClassName = $this->proxyFactory->createEntityProxy($className);
        }
        if (isset($this->entityFactories[$className])) {
            $entity = $this->entityFactories[$className]->createEntity($className);
            if ($requiresProxy) {
                $entity = $this->createProxyFromInstance($entity);
            }
        }
        
        if (!$entity) {
            $className = $requiresProxy ? $proxyClassName : $className;
            try {
                $entity = new $className();
            } catch (\Throwable $ex) {
                try {
                    //If constructor requires any objects, see if we can create them
                    $reflectionClass = new \ReflectionClass($className);
                    $constructor = $reflectionClass->getConstructor();
                    $constructorArgs = $constructor->getParameters();
                    foreach ($constructorArgs as $arg) {
                        if (!$arg->isOptional() && method_exists($arg->getType(), 'getName')) {
                            $reflectionType = $arg->getType();
                            $type = ObjectHelper::getTypeName($reflectionType);
                            $args[$arg->getName()] = $this->createEntity($type);
                        }
                    }
                    $entity = new $className(...$args);
                } catch (\Throwable $ex2) {
                    //That didn't work - just throw the original exception
                    throw $ex;
                }
            }
        }

        return $entity;
    }
    
    public function createProxyFromInstance(object $entity): ?EntityProxyInterface
    {
        try {
            $entityClassName = ObjectHelper::getObjectClassName($entity);
            $proxyClassName = $this->proxyFactory->createEntityProxy($entityClassName);
            $search = 'O:' . strlen($entityClassName) . ':"' . $entityClassName . '"';
            $replace = 'O:' . strlen($proxyClassName) . ':"' . $proxyClassName . '"';
            $serialized = serialize($entity);
            $hackedString = str_replace($search, $replace, $serialized);
            $proxy = unserialize($hackedString);
            $proxy = $proxy instanceof EntityProxyInterface ? $proxy : null;
        } catch (\Throwable $ex) {
            //Tried to use a custom entity factory for an entity that is not serializable :(
            $proxy = null;
        }

        return $proxy ?: null;
    }
}
