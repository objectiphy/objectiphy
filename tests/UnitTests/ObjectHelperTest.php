<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy;

use Objectiphy\Objectiphy\Orm\ProxyFactory;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestUser;
use Objectiphy\Objectiphy\Tests\Entity\TestWheel;
use PHPUnit\Framework\TestCase;
use Objectiphy\Objectiphy\ObjectHelper;

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
        $proxy = $proxyFactory->createEntityProxy(TestParent::class);
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
        $objectReference = $proxyFactory->createObjectReferenceProxy(TestChild::class, 142, 'id');
        $childId = ObjectHelper::getValueFromObject($objectReference, 'id');
        $this->assertEquals(142, $childId);
    }

    public function testReadWriteProxy()
    {
        $proxyFactory = new ProxyFactory();
        $parent = new TestParent();
        $proxyParent = $proxyFactory->createEntityProxy($parent);
        ObjectHelper::setValueOnObject($proxyParent, 'name', 'Fred');
        $this->assertEquals(true, isset($proxyParent->name));
        $this->assertEquals('Fred', $proxyParent->name);
        $this->assertEquals('Fred', $proxyParent->getEntity()->getName());
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
        ObjectHelper::setValueOnObject($parent, 'nameAlternative', 'Mogwai', null, '', false);
        $this->assertEquals('Stripe', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameWithOptionalExtraArg', 'Mogwai');
        $this->assertEquals('Mogwai', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameWithOptionalExtraArg', 'Gremlin', null, '', false);
        $this->assertEquals('Mogwai', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'nameInvalid', 'Gremlin');
        $this->assertEquals('Mogwai', $parent->getName());
    }

    public function testSetDateOnObject()
    {
        $policy = new TestPolicy();
        $testDate = new \DateTime('2019-08-01 09:00:01');
        ObjectHelper::setValueOnObject($policy, 'effectiveStartDateTime', $testDate->format('Y-m-d H:i:s'), 'datetime');
        $this->assertEquals($testDate, $policy->effectiveStartDateTime);
        $testDate->add(new \DateInterval('P10D'));
        ObjectHelper::setValueOnObject($policy, 'effectiveStartDateTime', $testDate, 'datetime');
        $this->assertEquals('2019-08-11', $policy->effectiveStartDateTime->format('Y-m-d'));
        ObjectHelper::setValueOnObject($policy, 'effectiveEndDateTime', $testDate);
        $this->assertEquals($testDate, $policy->effectiveEndDateTime);
        ObjectHelper::setValueOnObject($policy, 'effectiveEndDateTime', $testDate->format('Y-m-d H:i:s'));
        $this->assertEquals($testDate->format('Y-m-d H:i:s'), $policy->effectiveEndDateTime);
        ObjectHelper::setValueOnObject($policy, 'effectiveStartDateTime', $testDate, 'datetimestring');
        $this->assertEquals($testDate->format('Y-m-d H:i:s'), $policy->effectiveStartDateTime);
        ObjectHelper::setValueOnObject($policy, 'effectiveEndDateTime', $testDate->format('Y-m-d H:i:s'),
            'datetimestring', 'd/m/Y h:i:s');
        $this->assertEquals('11/08/2019 09:00:01', $policy->effectiveEndDateTime);
    }

    public function testSetIntOnObject()
    {
        $parent = new TestParent();
        ObjectHelper::setValueOnObject($parent, 'totalWeightOfPets', 120, 'int');
        $this->assertEquals(true, is_integer($parent->totalWeightOfPets));
        $this->assertEquals(120, $parent->totalWeightOfPets);
        ObjectHelper::setValueOnObject($parent, 'totalWeightOfPets', '54F2', 'integer');
        $this->assertEquals(true, is_integer($parent->totalWeightOfPets));
        $this->assertEquals(54, $parent->totalWeightOfPets);
    }

    public function testSetBoolOnObject()
    {
        $wheel = new TestWheel();
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', 'false', 'bool');
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(false, $wheel->loadBearing);
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', 1, 'boolean');
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(true, $wheel->loadBearing);
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', 0, 'boolean');
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(false, $wheel->loadBearing);
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', true, 'boolean');
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(true, $wheel->loadBearing);
        ObjectHelper::setValueOnObject($wheel, 'loadBearing', false, 'boolean');
        $this->assertEquals(true, is_bool($wheel->loadBearing));
        $this->assertEquals(false, $wheel->loadBearing);
    }

    public function testSetStringOnObject()
    {
        $parent = new TestParent();
        ObjectHelper::setValueOnObject($parent, 'name', 1234, 'string');
        $this->assertEquals(true, is_string($parent->getName()));
        $this->assertEquals('1234', $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', 1234.5, 'string', '%0.2f');
        $this->assertEquals(true, is_string($parent->getName()));
        $this->assertEquals('1234.50', $parent->getName());
    }

    public function testSetObjectsAndArrays()
    {
        $parent = new TestParent();
        ObjectHelper::setValueOnObject($parent, 'name', 'string', 'array');
        $this->assertEmpty($parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', ['string'], \Traversable::class);
        $this->assertEquals(['string'], $parent->getName());
        $traversable = new IterableResult($this->getMockBuilder(StorageInterface::class)->getMock());
        ObjectHelper::setValueOnObject($parent, 'name', $traversable, 'array');
        $this->assertEquals($traversable, $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', 'string', 'MadeUpClass');
        $this->assertEquals($traversable, $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', null, 'MadeUpClass');
        $this->assertEquals($traversable, $parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', null, TestParent::class);
        $this->assertEmpty($parent->getName());
        ObjectHelper::setValueOnObject($parent, 'name', $parent, TestParent::class);
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
        $proxyParent = $proxyFactory->createEntityProxy(TestParent::class);
        ObjectHelper::setValueOnObject($proxyParent, 'child', $closure);
        $this->assertEquals(true, $proxyParent->isChildAsleep('child'));
        $this->assertEquals('Gizmo', $proxyParent->child->getName());
    }

    public function testGetObjectClassName()
    {
        $proxyFactory = new ProxyFactory();
        $proxyParent = $proxyFactory->createEntityProxy(TestParent::class);
        $userReference = $proxyFactory->createObjectReferenceProxy(TestUser::class, 123, 'id');
        $child = new TestChild();

        $parentClass = ObjectHelper::getObjectClassName($proxyParent);
        $userClass = ObjectHelper::getObjectClassName($userReference);
        $childClass = ObjectHelper::getObjectClassName($child);

        $this->assertEquals(TestParent::class, $parentClass);
        $this->assertEquals(TestUser::class, $userClass);
        $this->assertEquals(TestChild::class, $childClass);
    }
}
