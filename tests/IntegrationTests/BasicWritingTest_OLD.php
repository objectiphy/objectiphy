<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\ProxyFactory;
use Objectiphy\Objectiphy\Tests\Entity\TestPet;
use Objectiphy\Objectiphy\Tests\Entity\TestUnderwriter;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;
use Objectiphy\Objectiphy\Tests\Entity\TestAddress;
use Objectiphy\Objectiphy\ObjectReference;

class ObjectRepositoryIntegrationTest extends IntegrationTestBase
{
    /**
     * Basic writing using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testWritingDefault()
    {
        $this->testName = 'Writing default';
        $this->doTests();
    }

    /**
     * Basic writing, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testWritingMixed()
    {
        $this->testName = 'Writing mixed';
        $this->objectRepository->setEagerLoad(true, false);
        $this->doTests();
    }

    /**
     * Basic writing, overriding annotations to always lazy load everything possible
     */
    public function testWritingLazy()
    {
        $this->testName = 'Writing lazy';
        $this->objectRepository->setEagerLoad(false, false);
        $this->doTests();
    }

    /**
     * Basic writing, overridding annotations to always eager load everything
     */
    public function testWritingEager()
    {
        $this->testName = 'Writing eager';
        $this->objectRepository->setEagerLoad(true, true);
        $this->doTests();
    }

    public function testSaveEmbeddedDirectly()
    {
        //You cannot save an embedded value object on its own - it needs a parent
        $newAddress2 = new TestAddress();
        $newAddress2->setTown('Chipping Sodbury');
        $newAddress2->setCountryCode('YY');
        $newAddress2->setCountryDescription('Absurdistan');
        $this->expectExceptionMessage('Failed to insert row');
        $this->objectRepository->saveEntity($newAddress2);
    }

    protected function doTests()
    {
        $this->doUpdateTests();
        $this->doInsertTestsOneToOne();
        $this->doInsertTestsOneToMany();
        $this->doMultipleInsertTests();
        $this->doClassInstanceUpdateTests();
        $this->doEmbeddedUpdateTests();
        $this->doReadOnlyTests();
        $this->doScalarJoinTests();
        $this->doEmbeddedValueObjectTests();
        $this->doSerializationGroupTests();
    }
    
    protected function doUpdateTests()
    {
        //Update an existing entity (will also update any child entities)
        $policy = $this->objectRepository->find(19071974);
        $policy->policyNo = 'TESTPOLICY UPDATED';
        $policy->contact->lastName = 'ChildUpdate';

        $recordsAffected = $this->objectRepository->saveEntity($policy);
        $this->assertEquals(2, $recordsAffected);

        //Verify update
        $policy2 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED', $policy2->policyNo);
        $this->assertEquals('ChildUpdate', $policy2->contact->lastName);

        //Update a parent entity without updating any child entities
        $policy2->policyNo = 'TESTPOLICY UPDATED AGAIN';
        $policy2->contact->lastName = 'IgnoreMe!';
        $this->objectRepository->saveEntity($policy2, false);
        
        //Verify update
        $policy3 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED AGAIN', $policy3->policyNo);
        $this->assertEquals('ChildUpdate', $policy3->contact->lastName);
        
        //Ensure the same result when using a proxy
        $proxyFactory = new ProxyFactory();
        $policy3a = $proxyFactory->createEntityProxy($policy3);
        $policy3a->policyNo = 'TESTPOLICY UPDATED YET AGAIN';
        $policy3a->contact->lastName = 'DoNotIgnoreMe!';
        $this->objectRepository->saveEntity($policy3a);
        
        //Verify update
        $policy4 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED YET AGAIN', $policy4->policyNo);
        $this->assertEquals('DoNotIgnoreMe!', $policy4->contact->lastName);

        $policy4a = $proxyFactory->createEntityProxy($policy4);
        $policy4a->policyNo = 'TESTPOLICY UPDATED ONE LAST TIME';
        $policy4a->contact->lastName = 'IgnoreMeAgain!';
        $this->objectRepository->saveEntity($policy4a, false);

        //Verify update
        $policy5 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED ONE LAST TIME', $policy5->policyNo);
        $this->assertEquals('DoNotIgnoreMe!', $policy5->contact->lastName);
    }
    
    protected function doInsertTestsOneToOne()
    {
        //Insert new entity (with child entities)
        $newPolicy = new TestPolicy();
        $newPolicy->policyNo = 'New!';
        $newPolicy->underwriter = new TestUnderwriter();
        $newPolicy->underwriter->id = 1;
        $newPolicy->effectiveStartDateTime = new \DateTime('today');
        $newPolicy->vehicle = new TestVehicle();
        $newPolicy->vehicle->policy = $newPolicy;
        $newPolicy->vehicle->regNo = 'NEW123';
        $newPolicy->contact = new TestContact();
        $newPolicy->contact->firstName = 'Frederick';
        $newPolicy->contact->lastName = 'Bloggs';

        $newEntityId = $this->objectRepository->saveEntity($newPolicy);
        $this->assertGreaterThan(0, $newEntityId);
        $refreshedNewPolicy = $this->objectRepository->find($newEntityId);
        $this->assertEquals('NEW123', $refreshedNewPolicy->vehicle->regNo);

        //Add existing child entity to new parent entity
        $contact = new TestContact();
        $random = uniqid();
        $contact->firstName = 'Existing';
        $contact->lastName = 'Contact' . $random;
        $this->objectRepository->saveEntity($contact);
        $newPolicy2 = new TestPolicy();
        $newPolicy2->policyNo = 'Test2';
        $newPolicy2->underwriter = $newPolicy->underwriter;
        //We could just use the $contact object directly here, but for testing purposes we will use an object reference.
        $newPolicy2->contact = $this->objectRepository->getObjectReference(TestContact::class, $contact->id);
        $newPolicy2->vehicle = new TestVehicle();
        $newPolicy2->vehicle->policy = new ObjectReference($newPolicy2);
        $newPolicy2Id = $this->objectRepository->saveEntity($newPolicy2);

        //Verify save
        $refreshedPolicy = $this->objectRepository->findOneBy(['policyNo' => 'Test2']);
        $this->assertEquals('Existing Contact' . $random, $refreshedPolicy->contact->getName());

        //And update property on child
        $policy = $this->objectRepository->findOneBy(['policyNo' => 'Test2']);
        $policy->contact->firstName = 'Deedpoll';
        $newPolicyId = $this->objectRepository->saveEntity($policy);
        $this->assertGreaterThan(0, $newPolicyId);

        //Let's use the $contact object directly as well...
        $contact2 = new TestContact();
        $random = uniqid();
        $contact2->firstName = 'Existing';
        $contact2->lastName = 'Contact' . $random;
        $this->objectRepository->saveEntity($contact2);
        $newPolicy2a = new TestPolicy();
        $newPolicy2a->policyNo = 'Test2a';
        $newPolicy2a->underwriter = $newPolicy->underwriter;
        $newPolicy2a->contact = $contact2;
        $newPolicy2a->vehicle = new TestVehicle();
        $newPolicy2a->vehicle->policy = new ObjectReference($newPolicy2a);
        $newPolicy2aId = $this->objectRepository->saveEntity($newPolicy2a);

        //Verify save
        $refreshedPolicy2a = $this->objectRepository->findOneBy(['policyNo' => 'Test2a']);
        $this->assertEquals('Existing Contact' . $random, $refreshedPolicy2a->contact->getName());
        $this->assertEquals($newPolicy2Id + 1, $newPolicy2aId);

        //Insert new child object on existing entity
        $newPolicy2 = $this->objectRepository->findOneBy(['policyNo' => 'Test2']);
        $newPolicy2->contact = new TestContact();
        $newPolicy2->contact->firstName = 'Newchild';
        $newPolicy2->contact->lastName = 'Smith';
        $this->objectRepository->saveEntity($newPolicy2);
        $refreshedPolicy2 = $this->objectRepository->findOneBy(['policyNo' => 'Test2']);
        $this->assertEquals('Newchild Smith', $refreshedPolicy2->contact->getName());
        $this->assertEquals('Test2', $refreshedPolicy2->policyNo);
        $this->assertEquals(true, $refreshedPolicy2->loginId === null);
    }

    protected function doInsertTestsOneToMany()
    {
        //Insert children on a one-to-many relationship (new parent, new children)
        $this->objectRepository->setEntityClassName(TestParent::class);
        $parent = new TestParent();
        $parent->setName('A new parent');
        $this->objectRepository->saveEntity($parent);
        $this->assertGreaterThan(0, $parent->getId());
        $newPet = new TestPet();
        $newPet->type = 'chicken';
        $newPet->name = 'Nugget';
        $newPet->weightInGrams = 625;
        $newPet2 = new TestPet();
        $newPet2->type = 'rabbit';
        $newPet2->name = 'Sniff';
        $newPet2->weightInGrams = 3750;
        $parent->getPets()->append($newPet);
        $parent->getPets()->append($newPet2);
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find($parent->getId());
        $this->assertEquals(2, count($refreshedParent->getPets()));
        $this->assertEquals('Nugget', $refreshedParent->getPets()[0]->name);
        $this->assertEquals(4375, $refreshedParent->totalWeightOfPets);

        //Update child on a one-to-many relationship
        $refreshedParent->getPets()[0]->weightInGrams += 50;
        $this->objectRepository->saveEntity($refreshedParent);
        $refreshedParent2 = $this->objectRepository->find($parent->getId());
        $this->assertEquals(4425, $refreshedParent2->totalWeightOfPets);

        //Insert AND update children on a one-to-many relationship
        $newPet3 = new TestPet();
        $newPet3->type = 'chicken';
        $newPet3->name = 'Fifi';
        $newPet3->weightInGrams = 721;
        $refreshedParent2->getPets()[1]->name = 'Snuff';
        $refreshedParent2->getPets()->append($newPet3);
        $this->objectRepository->saveEntity($refreshedParent2);
        $refreshedParent3 = $this->objectRepository->find($parent->getId());
        $this->assertEquals(3, count($refreshedParent3->getPets()));
        $this->assertEquals(5146, $refreshedParent3->totalWeightOfPets);
        $this->assertEquals('Fifi', $refreshedParent3->getPets()[0]->name);
        $this->assertEquals('Nugget', $refreshedParent3->getPets()[1]->name);
        $this->assertEquals('Snuff', $refreshedParent3->getPets()[2]->name);

        //Add new children to a new parent
        $newPetA = new TestPet();
        $newPetA->type = 'cat';
        $newPetA->name = 'Arnie';
        $newPetA->weightInGrams = 2755;
        $newPetB = new TestPet();
        $newPetB->type = 'dog';
        $newPetB->name = 'Scamp';
        $newPetB->weightInGrams = 9855;
        $newParent = new TestParent();
        $newParent->setName('Christopher Hitchens');
    }
    
    protected function doMultipleInsertTests()
    {
        $this->objectRepository->setEntityClassName(TestPolicy::class);
        $policy = $this->objectRepository->find(19071975);

        //Insert multiple entities
        $policies = [];
        $policy1 = new TestPolicy();
        $policy1->policyNo = 'First';
        $policy1->underwriter = $policy->underwriter;
        $policy1->vehicle = new TestVehicle();
        $policy1->vehicle->policy = new ObjectReference($policy1);
        $policy1->vehicle->regNo = 'Reg1';
        $policies[] = $policy1;
        $policy2 = new TestPolicy();
        $policy2->policyNo = 'Second';
        $policy2->underwriter = $policy->underwriter;
        $policy2->vehicle = new TestVehicle();
        $policy2->vehicle->policy = new ObjectReference($policy2);
        $policy2->vehicle->regNo = 'Reg2';
        $policies[] = $policy2;
        $policy3 = new TestPolicy();
        $policy3->policyNo = 'Third';
        $policy3->underwriter = $policy->underwriter;
        $policy3->vehicle = new TestVehicle();
        $policy3->vehicle->policy = new ObjectReference($policy3);
        $policy3->vehicle->regNo = 'Reg3';
        $policies[] = $policy3;
        $results = $this->objectRepository->saveEntities($policies);
        $this->assertEquals(3, count($results));
    }
    
    protected function doClassInstanceUpdateTests()
    {
        //Save data to two different instances of the same class
        $this->objectRepository->setEntityClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $parent->getUser()->setType('branch2');
        $parent->getChild()->getUser()->setType('staff2');
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find(1);
        $this->assertNotEquals($refreshedParent->getUser()->getId(), $refreshedParent->getChild()->getUser()->getId());
        $this->assertEquals('branch2', $refreshedParent->getUser()->getType());
        $this->assertEquals('staff2', $refreshedParent->getChild()->getUser()->getType());
    }
    
    protected function doEmbeddedUpdateTests()
    {
        //Update a property on an embedded value object
        $this->objectRepository->setEntityClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $add = $parent->getAddress();
        $parent->getAddress()->setTown('Somewhereborough');
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find(1);
        $this->assertEquals('Somewhereborough', $refreshedParent->getAddress()->getTown());
    }
    
    protected function doReadOnlyTests()
    {
        //Check default readOnly behaviour...
        //1) Normal scalar property (writable - already tested)
        //2) Normal child object property (writable - already tested)
        //3) Scalar join target value (value/description) (not writable)
        //4) Scalar join source value (key) (writable)
        $this->objectRepository->setEntityClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $parent->getAddress()->setCountryDescription('Mos Eisley');
        $this->objectRepository->saveEntity($parent);
        $unrefreshedParent = $this->objectRepository->find(1);
        $this->assertEquals('United Kingdom', $unrefreshedParent->getAddress()->getCountryDescription());
        $parent->getAddress()->setCountryCode('EU');
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find(1);
        $this->assertEquals('Somewhere in Europe', $refreshedParent->getAddress()->getCountryDescription());

        //Override default readOnly behaviour
        $this->objectRepository->setColumnOverrides(TestAddress::class,
            ['countryDescription' => ['isReadOnly' => false]]);

        //Update scalar join value (keep code the same)
        $refreshedParent->getAddress()->setCountryDescription('Mos Eisley');
        $this->objectRepository->saveEntity($refreshedParent);
        $refreshedParent2 = $this->objectRepository->find(1);
        $this->assertEquals('EU', $refreshedParent2->getAddress()->getCountryCode());
        $this->assertEquals('Mos Eisley', $refreshedParent2->getAddress()->getCountryDescription());

        //Insert new scalar join value
        $refreshedParent2->getAddress()->setCountryCode('ZZ');
        $refreshedParent2->getAddress()->setCountryDescription('Middle Earth');
        $this->objectRepository->saveEntity($refreshedParent2);
        $refreshedParent3 = $this->objectRepository->find(1);
        $this->assertEquals('ZZ', $refreshedParent3->getAddress()->getCountryCode());
        $this->assertEquals('Middle Earth', $refreshedParent3->getAddress()->getCountryDescription());
    }
    
    protected function doScalarJoinTests()
    {
        $this->objectRepository->setEntityClassName(TestParent::class);
        //Revert to default readOnly behaviour
        $this->objectRepository->setColumnOverrides(TestAddress::class,
            ['countryDescription' => ['isReadOnly' => null]]);

        //Insert new entity with existing scalar join value
        $newParent = new TestParent();
        $newChild = new TestChild();
        $newAddress = new TestAddress();
        $newAddress->setTown('Gotham');
        $newAddress->setCountryCode('XX');
        $newAddress->setCountryDescription('This should have no effect!');
        $newChild->setName('Marvin');
        $newChild->address = new TestAddress();
        $newChild->address->setTown('São Vicente');
        $newChild->address->setCountryCode('CV');

        $newParent->setName('Arthur Dent');
        $newParent->setChild($newChild);
        $newParent->setAddress($newAddress);
        $newParentId = $this->objectRepository->saveEntity($newParent);
        $this->assertGreaterThan(0, $newParentId);
        $refreshedNewParent = $this->objectRepository->find($newParentId);
        $this->assertEquals('Deepest Darkest Peru', $refreshedNewParent->getAddress()->getCountryDescription());
        $this->assertEquals('CV', $refreshedNewParent->getChild()->address->getCountryCode());

        //Insert new entity with new scalar join value
        $newAddress2 = new TestAddress();
        $newAddress2->setTown('Chipping Sodbury');
        $newAddress2->setCountryCode('YY');
        $newAddress2->setCountryDescription('Absurdistan');
        $newParent2 = new TestParent();
        $newParent2->setAddress($newAddress2);
        $newParent2Id = $this->objectRepository->saveEntity($newParent2);
        $refreshedNewParent2 = $this->objectRepository->find($newParent2Id);
        $this->assertEquals('YY', $refreshedNewParent2->getAddress()->getCountryCode());

        //By default, the description won't save due to the safe default read-only behaviour
        $this->assertEquals(null, $refreshedNewParent2->getAddress()->getCountryDescription());
        //Set read only to false, so we can save the description
        $this->objectRepository->setColumnOverrides(TestAddress::class,
            ['countryDescription' => ['isReadOnly' => false]]);
        $newParent3 = new TestParent();
        $newParent3->setAddress($newAddress2);
        $newChild3 = new TestChild();
        $newChild3->address = new TestAddress();
        $newParent3->setChild($newChild3);
        $newParent3->getChild()->address->setCountryCode('CV');
        $newParent3->getChild()->address->setCountryDescription('Cabo Verde'); //Scalar join on embedded object with column prefix
        $newParent3Id = $this->objectRepository->saveEntity($newParent3);
        $refreshedNewParent3 = $this->objectRepository->find($newParent3Id);
        $this->assertEquals('Absurdistan', $refreshedNewParent3->getAddress()->getCountryDescription());
        $this->assertEquals('Cabo Verde', $refreshedNewParent3->getChild()->address->getCountryDescription());

        //Override readOnly behaviour for non-scalar-join properties
        $this->objectRepository->setColumnOverrides(TestAddress::class,
            ['countryDescription' => ['isReadOnly' => null]]);
        $this->objectRepository->setColumnOverrides(TestChild::class, ['height' => ['isReadOnly' => false]]);
        $refreshedNewParent->getChild()->setHeight('48');

        $this->objectRepository->saveEntity($refreshedNewParent);
        $refreshedNewParent4 = $this->objectRepository->find($refreshedNewParent->getId());
        $this->assertEquals(48, $refreshedNewParent4->getChild()->getHeight());
        $this->objectRepository->setColumnOverrides(TestChild::class, ['height' => ['isReadOnly' => true]]);
        $refreshedNewParent4->getChild()->setHeight(49);
        $this->objectRepository->saveEntity($refreshedNewParent4);
        $refreshedNewParent5 = $this->objectRepository->find($refreshedNewParent4->getId());
        $this->assertEquals(48, $refreshedNewParent5->getChild()->getHeight());
        $this->objectRepository->setColumnOverrides(TestChild::class, ['height' => ['isReadOnly' => null]]);

        //Check that new mapping properties work on normal relationship joins (not just scalar joins)
        $this->objectRepository->setColumnOverrides(TestChild::class, [
            'user' => [
                'joinTable' => 'objectiphy_test.user_alternative',
                'sourceJoinColumn' => 'user_id',
                'joinColumn' => 'id'
            ]
        ]);
        $altParent = $this->objectRepository->find(1);
        $this->assertEquals('alternative2@example.com', $altParent->getChild()->getUser()->getEmail());
    }
    
    protected function doEmbeddedValueObjectTests()
    {
        $this->objectRepository->setEntityClassName(TestParent::class);
        //Remove an embedded value object
        $parent = $this->objectRepository->find(1);
        $parent->setAddress(null);
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find(1);
        $this->assertEquals(null, $refreshedParent->getAddress());

        //Add a new embedded value object
        $newAddress = new TestAddress();
        $newAddress->setTown('Waffleville');
        $refreshedParent->setAddress($newAddress);
        $this->objectRepository->saveEntity($refreshedParent);
        $refreshedParent2 = $this->objectRepository->find(1);
        $this->assertEquals('Waffleville', $refreshedParent2->getAddress()->getTown());
    }
    
    protected function doSerializationGroupTests()
    {
        $this->objectRepository->setEntityClassName(TestParent::class);
        //When saving an entity that was only partially loaded (due to serialization groups), do not try to save the unhydrated properties
        $this->objectRepository->clearSerializationGroups();
        $this->objectRepository->addSerializationGroups(['Default', 'Full']); //Not 'Special', which is used on the child name property
        /** @var $parent TestParent */
        $parent = $this->objectRepository->find(1);
        $this->assertEquals(null, $parent->getChild()->getName()); //We did not hydrate the child name
        $parent->setName('Updated Parent Name!');
        $this->objectRepository->saveEntity($parent);

        $this->objectRepository->clearSerializationGroups();
        /** @var $updatedParent TestParent */
        $updatedParent = $this->objectRepository->find(1);
        $this->assertEquals('Updated Parent Name!', $updatedParent->getName()); //Parent name update successful
        $this->assertNotEquals(null, $updatedParent->getChild()); //Child was not lost
        $this->assertEquals('Penfold', $updatedParent->getChild()->getName()); //Child name was not lost
    }
}
