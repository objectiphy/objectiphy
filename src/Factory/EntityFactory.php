<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Factory;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;

/**
 * @package Objectiphy\Objectiphy
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

    public function createEntity(string $className, bool $requiresProxy = false): object
    {
        $entity = null;
        if ($requiresProxy) {
            $proxyClassName = $this->proxyFactory->createEntityProxy($className);
        }
        if (isset($this->entityFactories[$className])) {
            $entity = $this->entityFactories[$className]->createEntity($className);
            if ($requiresProxy) {
                $entity = $this->createProxyFromInstance($proxyClassName, $entity);
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
                            $type = $arg->getType()->getName();
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
    
    public function createProxyFromInstance(string $proxyClassName, object $entity): ?EntityProxyInterface
    {
        try {
            $serialized = serialize($entity);
            $length = strlen($proxyClassName);
            $hackedString = preg_replace('/^O:\d+:"[^"]++"/', "O:$length:\"$proxyClassName\"", $serialized);
            $proxy = unserialize($hackedString);
        } catch (\Throwable $ex) {
            //Tried to use a custom entity factory for an entity that is not serializable :(
            $proxy = null;
        }
        
        return $proxy ?: null;
    }
}
