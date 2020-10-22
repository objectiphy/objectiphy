<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class EntityFactory implements EntityFactoryInterface
{
    private array $entityFactories = [];

    public function registerCustomEntityFactory(string $className, EntityFactoryInterface $entityFactory)
    {
        $this->entityFactories[$className] = $entityFactory;
    }

    public function createEntity(string $className): object
    {
        if (isset($this->entityFactories[$className])) {
            $entity = $this->entityFactories[$className]->createEntity($className);
        } else {
            try {
                $entity = new $className();
            } catch (\Exception $ex) {
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
                } catch (\Exception $ex2) {
                    //That didn't work - just throw the original exception
                    throw $ex;
                }
            }
        }

        return $entity;
    }
}
