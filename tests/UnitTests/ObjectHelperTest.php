<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy;

use Objectiphy\Objectiphy\Factory\ProxyFactory;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestUser;
use Objectiphy\Objectiphy\Tests\Entity\TestWheel;
use PHPUnit\Framework\TestCase;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

class ObjectHelperTest extends TestCase
{
    public function testReadPropertyFromObject()
    {
        $object = new TestParent();
        $object->setName('Zainab');
        $this->assertEquals('nothing', ObjectHelper::getValueFromObject($object, 'nameAlternative', 'nothing', false));
        $this->assertEquals(false, $object->wasAltNameGetterAccessed());
        $this->assertEquals('Zainab', ObjectHelper::getValueFromObject($object, 'nameAlternative'));
        $this->assertEquals(true, $object->wasAltNameGetterAccessed());
        $this->assertEquals('Zainab', ObjectHelper::getValueFromObject($object, 'name'));
        $this->assertEquals(false, $object->wasNameGetterAccessed());
        $this->assertEquals('Zainab', ObjectHelper::getValueFromObject($object, 'name', 'nothing', false));
    }

    public function testReadLazyLoad()
    {
        $proxyFactory = new ProxyFactory();
        $proxyClass = $proxyFactory->createEntityProxy(TestParent::class);
        $proxy = new $proxyClass();
        $proxy->setLazyLoader('child', function () {
            $child = new TestChild();
            $child->setName('Gizmo');
            return $child;
        });
        $this->assertEquals(true, $proxy->isChildAsleep('child'));
        $child = ObjectHelper::getValueFromObject($proxy, 'child');
        $this->assertEquals(false, $proxy->isChildAsleep('child'));
        $this->assertEquals('Gizmo', $child->getName());
    }

    public function testReadKeyFromObjectReference()
    {
        $proxyFactory = new ProxyFactory();
        $objectReference = $proxyFactory->createObjectReferenceProxy(TestChild::class, ['id' => 142]);
        $childId = ObjectHelper::getValueFromObject($objectReference, 'id');
        $this->assertEquals(142, $childId);
    }

    public function testReadWriteProxy()
    {
        $proxyFactory = new ProxyFactory();
        $proxyParentClass = $proxyFactory->createEntityProxy(TestParent::class);
        $proxyParent = new $proxyParentClass;
        ObjectHelper::setValueOnObject($proxyParent, 'name', 'Fred');
        $this->assertEquals(true, isset($proxyParent->name));
        $this->assertEquals('Fred', $proxyParent->name);
    }

    public function testSetValueOnObject()
    {
        $parent = new TestParent();
        ObjectHelper::setValueOnObject($parent, 'name', 'Billy');
        $this->assertEquals(false, $parent->wasNameSetterAccessed());
        $this->assertEquals('Billy', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameAlternative', 'Stripe');
        $this->assertEquals(true, $parent->wasAltNameSetterAccessed());
        $this->assertEquals('Stripe', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameAlternative', 'Mogwai', false);
        $this->assertEquals('Stripe', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameWithOptionalExtraArg', 'Mogwai');
        $this->assertEquals('Mogwai', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameWithOptionalExtraArg', 'Gremlin', false);
        $this->assertEquals('Mogwai', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameInvalid', 'Gremlin');
        $this->assertEquals('Mogwai', $parent->getName());
    }

    public function testSetDateOnObject()
    {
        $policy = new TestPolicy();
        $testDate = new \DateTime('2019-08-11 09:00:01');
        ObjectHelper::setValueOnObject($policy, 'effectiveStartDateTime', $testDate);
        $this->assertEquals('2019-08-11', $policy->effectiveStartDateTime->format('Y-m-d'));
        ObjectHelper::setValueOnObject($policy, 'effectiveEndDateTime', $testDate);
        $this->assertEquals($testDate, $policy->effectiveEndDateTime);
        ObjectHelper::setValueOnObject($policy, 'effectiveEndDateTime', $testDate->format('Y-m-d H:i:s'));
        $this->assertEquals($testDate->format('Y-m-d H:i:s'), $policy->effectiveEndDateTime);
    }

    public function testSetIntOnObject()
    {
        $parent = new TestParent();
        ObjectHelper::setValueOnObject($parent, 'id', 120);
        $this->assertEquals(true, is_integer($parent->getId()));
        $this->assertEquals(120, $parent->getId());
    }

    public function testSetBoolOnObject()
    {
        $wheel = new TestWheel();
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', true);
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(true, $wheel->loadBearing);
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', false);
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(false, $wheel->loadBearing);
    }

    public function testSetObjectsAndArrays()
    {
        $parent = new TestParent();
        ObjectHelper::setValueOnObject($parent, 'name', ['string']);
        $this->assertEquals(['string'], $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', $parent);
        $this->assertEquals($parent, $parent->getName());
    }

    public function testSetLazyLoader()
    {
        $parent = new TestParent();
        $closure = function () {
            $child = new TestChild();
            $child->setName('Gizmo');
            return $child;
        };
        ObjectHelper::setValueOnObject($parent, 'child', $closure);
        $this->assertInstanceOf(\Closure::class, $parent->getChild());

        $proxyFactory = new ProxyFactory();
        $proxyParentClass = $proxyFactory->createEntityProxy(TestParent::class);
        $proxyParent = new $proxyParentClass();
        ObjectHelper::setValueOnObject($proxyParent, 'child', $closure);
        $this->assertEquals(true, $proxyParent->isChildAsleep('child'));
        $this->assertEquals('Gizmo', $proxyParent->child->getName());
    }

    public function testGetObjectClassName()
    {
        $proxyFactory = new ProxyFactory();
        $proxyParentClass = $proxyFactory->createEntityProxy(TestParent::class);
        $proxyParent = new $proxyParentClass();
        $userReference = $proxyFactory->createObjectReferenceProxy(TestUser::class, ['id' => 123]);
        $child = new TestChild();

        $parentClass = ObjectHelper::getObjectClassName($proxyParent);
        $userClass = ObjectHelper::getObjectClassName($userReference);
        $childClass = ObjectHelper::getObjectClassName($child);

        $this->assertEquals(TestParent::class, $parentClass);
        $this->assertEquals(TestUser::class, $userClass);
        $this->assertEquals(TestChild::class, $childClass);
    }
}
