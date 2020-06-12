<?php

namespace Objectiphy\Objectiphy\Tests\Factory;

use Objectiphy\Objectiphy\EntityFactoryInterface;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;

class TestVehicleFactory implements EntityFactoryInterface
{
    public function createEntity($entityName = null)
    {
        if ($entityName == TestVehicle::class) {
            $vehicle = new TestVehicle('This has been created by a factory!');

            return $vehicle;
        }
    }
}
