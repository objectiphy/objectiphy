<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Factory\EntityFactory;
use Objectiphy\Objectiphy\Factory\ProxyFactory;
use Objectiphy\Objectiphy\Orm\ObjectReference;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Tests\Entity\TestAddress;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestEmployee;
use Objectiphy\Objectiphy\Tests\Entity\TestPet;
use Objectiphy\Objectiphy\Tests\Entity\TestSecurityPass;
use Objectiphy\Objectiphy\Tests\Entity\TestSuppliedPk;
use Objectiphy\Objectiphy\Tests\Entity\TestUnderwriter;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;

class BasicWritingTest extends IntegrationTestBase
{
    /**
     * Basic writing using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testWritingDefault()
    {
        $this->testName = 'Writing default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Basic writing, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testWritingMixed()
    {
        $this->testName = 'Writing mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Basic writing, overriding annotations to always lazy load everything possible
     */
    public function testWritingLazy()
    {
        $this->testName = 'Writing lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Basic writing, overridding annotations to always eager load everything
     */
    public function testWritingEager()
    {
        $this->testName = 'Writing eager' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    public function testSaveEmbeddedDirectly()
    {
        //You cannot save an embedded value object on its own - it needs a parent
        $newAddress2 = new TestAddress();
        $newAddress2->setTown('Chipping Sodbury');
        $newAddress2->setCountryCode('YY');
        $newAddress2->setCountryDescription('Absurdistan');
        $this->expectExceptionMessage('no table mapping');
        $this->objectRepository->saveEntity($newAddress2);
    }

    //Repeat with cache turned off
    public function testWritingDefaultNoCache()
    {
        $this->disableCache();
        $this->testWritingDefault();
    }

    public function testWritingMixedNoCache()
    {
        $this->disableCache();
        $this->testWritingMixed();
    }

    public function testWritingLazyNoCache()
    {
        $this->disableCache();
        $this->testWritingLazy();
    }

    public function testWritingEagerNoCache()
    {
        $this->disableCache();
        $this->testWritingEager();
    }

    protected function doTests()
    {
        $this->doUpdateTests();
        $this->doParentOnlyTests();
        $this->doPrivatePropertyTests();
        $this->doReplacementTests();
        $this->doInsertTestsOneToOne();
        $this->doInsertTestsOneToMany();
        $this->doMultipleInsertTests();
        $this->doClassInstanceUpdateTests();
        $this->doEmbeddedUpdateTests();
        $this->doReadOnlyTests();
        $this->doScalarJoinTests();
        $this->doMappingOverrideTests();
        $this->doDataMapTests();
        $this->doEmbeddedValueObjectTests();
        $this->doSerializationGroupTests();
        $this->doUnidirectionalScalarRelationshipTests();
    }
    
    protected function doUpdateTests()
    {
        //Update an existing entity (will also update any child entities)
        $this->objectRepository->setClassName(TestPolicy::class);
        $policy = $this->objectRepository->find(19071974);
        $policy->policyNo = 'TESTPOLICY UPDATED';
        $policy->contact->lastName = 'ChildUpdate';
        $recordsAffected = $this->objectRepository->saveEntity($policy);
        $recordsAffected = 2;
        if ($this->getCacheSuffix()) {
            $this->assertGreaterThanOrEqual(2, $recordsAffected);
        } else {
            $this->assertEquals(2, $recordsAffected);
        }

        //Verify update
        $policy2 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED', $policy2->policyNo);
        $this->assertEquals('ChildUpdate', $policy2->contact->lastName);

        //If we tell it not to update children, ensure foreign keys are ignored
        $policy = $this->objectRepository->find(19071974);
        $policy->vehicle->policy = null;
        $policy->contact = null;
        $this->objectRepository->saveEntity($policy, false);
        $this->objectRepository->clearCache();
        $refreshedPolicy = $this->objectRepository->find(19071974);
        $this->assertEquals(123, $refreshedPolicy->contact->id);
        $this->assertEquals(1, $refreshedPolicy->vehicle->id);
        $this->assertEquals(19071974, $refreshedPolicy->vehicle->policy->id);

        //And if we tell it to update children, they are not ignored
        //(known issue: if child owns relationship, the relationship won't be deleted unless you save the child
        //directly - hence we don't check for a null vehicle or vehicle->policy here, as it will not have removed the
        //relationship)
        $this->objectRepository->saveEntity($policy, true);
        $this->objectRepository->clearCache();
        $refreshedPolicy = $this->objectRepository->find(19071974);
        $this->assertNull($refreshedPolicy->contact);

        //Put the contact back, ready for the next test (quicker than running $this->setUp() again)
        $refreshedPolicy->contact = $this->objectRepository->getObjectReference(TestContact::class, ['id' => 123]);
        $this->objectRepository->saveEntity($refreshedPolicy);
    }

    protected function doParentOnlyTests()
    {
        $policy2 = $this->objectRepository->find(19071974);

        //Update a parent entity without updating any child entities
        $policy2->policyNo = 'TESTPOLICY UPDATED AGAIN';
        $policy2->contact->lastName = 'IgnoreMe!';
        $this->objectRepository->saveEntity($policy2, false);

        //Verify update
        if ($this->objectRepository->getConfiguration()->eagerLoadToOne && $this->objectRepository->getConfiguration()->eagerLoadToMany) {
            //Doctrine annotation will set lazy load mapping anyway, so we have to override it
            $this->objectRepository->setEntityConfigOption(
                TestVehicle::class,
                ConfigEntity::RELATIONSHIP_OVERRIDES,
                ['telematicsBox' => ['lazyLoad' => null]]
            );
            $this->objectRepository->setEntityConfigOption(
                TestVehicle::class,
                ConfigEntity::RELATIONSHIP_OVERRIDES,
                ['wheels' => ['lazyLoad' => null]]
            );
            //This one is not necessary, but we do it to test that clearing the mapping cache for the main entity doesn't break anything.
            $this->objectRepository->setEntityConfigOption(
                TestPolicy::class,
                ConfigEntity::RELATIONSHIP_OVERRIDES,
                ['vehicle' => ['lazyLoad' => null]]
            );
        }
        $this->objectRepository->clearCache(); //Necessary to force refresh from database as we did not save the child

        $policy3 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED AGAIN', $policy3->policyNo);
        $this->assertEquals('ChildUpdate', $policy3->contact->lastName);

        //Try to update two fields in a single query
        $query = QB::create()
            ->update(TestPolicy::class)
            ->innerJoin(TestContact::class, 'c')
                ->on('contact', '=', 'c.id')
            ->set(['policyNo' => 'TP6', 'c.lastName' => 'LastName6'])
            ->where('id', QB::EQ, 19071974)
            ->buildUpdateQuery();
        $affectedCount = $this->objectRepository->executeQuery($query);
        $this->assertEquals(2, $affectedCount);

        //Ensure the same result when using a proxy
        $this->assertInstanceOf(EntityProxyInterface::class, $policy3->vehicle);
        $policy3->vehicle->regNo = 'UpdatedRegNo';
        $policy3->vehicle->policy->policyNo = 'TESTPOLICY UPDATED YET AGAIN';
        $this->objectRepository->saveEntity($policy3->vehicle);

        //Verify update
        $policy4 = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED YET AGAIN', $policy4->policyNo);
        $this->assertEquals('UpdatedRegNo', $policy4->vehicle->regNo);

        //Disable child updates on a proxy
        $this->assertInstanceOf(EntityProxyInterface::class, $policy3->vehicle);
        $policy3->vehicle->regNo = 'UpdatedRegNoTwo';
        $policy3->vehicle->policy->policyNo = 'IgnoreMe!';
        $this->objectRepository->saveEntity($policy3->vehicle, false);

        //Verify update
        $this->objectRepository->clearCache(); //Necessary to force refresh from database as we did not save the child
        $policy4a = $this->objectRepository->find(19071974);
        $this->assertEquals('TESTPOLICY UPDATED YET AGAIN', $policy4a->policyNo);
        $this->assertEquals('UpdatedRegNoTwo', $policy4a->vehicle->regNo);

        //Save a proxy
        $proxyFactory = new ProxyFactory();
        $entityFactory = new EntityFactory($proxyFactory);
        if ($this->objectRepository->getConfiguration()->eagerLoadToOne && $this->objectRepository->getConfiguration()->eagerLoadToMany) {
            $policy3a = $entityFactory->createProxyFromInstance($policy3);
            $policy3a->policyNo = 'TESTPOLICY UPDATED YET AGAIN';
            $policy3a->contact->lastName = 'DoNotIgnoreMe!';
            $this->objectRepository->saveEntity($policy3a);

            //Verify update
            $policy4 = $this->objectRepository->find(19071974);
            $this->assertEquals('TESTPOLICY UPDATED YET AGAIN', $policy4->policyNo);
            $this->assertEquals('DoNotIgnoreMe!', $policy4->contact->lastName);

            $policy4a = $entityFactory->createProxyFromInstance($policy4);
            $policy4a->policyNo = 'TESTPOLICY UPDATED ONE LAST TIME';
            $policy4a->contact->lastName = 'IgnoreMeAgain!';
            $this->objectRepository->saveEntity($policy4a, false);

            //Verify update
            $this->objectRepository->clearCache(); //Change not saved
            $policy5 = $this->objectRepository->find(19071974);
            $this->assertEquals('TESTPOLICY UPDATED ONE LAST TIME', $policy5->policyNo);
            $this->assertEquals('DoNotIgnoreMe!', $policy5->contact->lastName);
        }
    }

    protected function doPrivatePropertyTests()
    {
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $this->assertNotEmpty($parent->hasModifiedDateTimeBeenSet());
        $parent->aSetterForModifiedDateTimeWithoutSetPrefix(new \DateTime('2021-01-01'));
        $this->objectRepository->saveEntity($parent);

        //Verify
        $parent2 = $this->objectRepository->find(1);
        $this->assertEquals('2021-01-01', ($parent2->hasModifiedDateTimeBeenSet())->format('Y-m-d'));
    }

    protected function doReplacementTests()
    {
        $this->objectRepository->setClassName(TestSuppliedPk::class);
        $suppliedPk = new TestSuppliedPk();
        $suppliedPk->keyReference = 'C54321';
        $suppliedPk->someValue = 'New value';
        $insertCount = 0;
        $updateCount = 0;
        $insertCount = $this->objectRepository->saveEntity($suppliedPk, null, null, $insertCount, $updateCount);
        $this->assertEquals(1, $insertCount);
        $this->assertEquals(0, $updateCount);

        $loadedPk = $this->objectRepository->findOneBy(['keyReference' => 'C54321']);
        $this->assertEquals('New value', $loadedPk->someValue);

        //Make sure it does not attempt to replace if autoIncrement is true
        $this->objectRepository->setEntityConfigOption(
            TestSuppliedPk::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['keyReference' => ['autoIncrement' => true]]
        );
        $suppliedPk = new TestSuppliedPk();
        $suppliedPk->keyReference = 'D54321';
        $suppliedPk->someValue = 'New value 2';
        $insertCount = 0;
        $updateCount = 0;
        $insertCount = $this->objectRepository->saveEntity($suppliedPk, null, null, $insertCount, $updateCount);
        $this->assertEquals(0, $insertCount);
        $this->assertEquals(0, $updateCount);

        $loadedPk = $this->objectRepository->findOneBy(['keyReference' => 'D54321']);
        $this->assertNull($loadedPk);

        //Make sure it does attempt to replace if autoIncrement is true AND we tell it to replace
        $suppliedPk = new TestSuppliedPk();
        $suppliedPk->keyReference = 'E54321';
        $suppliedPk->someValue = 'New value 3';
        $insertCount = 0;
        $updateCount = 0;
        $insertCount = $this->objectRepository->saveEntity($suppliedPk, null, true, $insertCount, $updateCount);
        $this->assertEquals(1, $insertCount);
        $this->assertEquals(0, $updateCount);

        $loadedPk = $this->objectRepository->findOneBy(['keyReference' => 'E54321']);
        $this->assertEquals('New value 3', $loadedPk->someValue);
    }

    protected function doInsertTestsOneToOne()
    {
        $this->objectRepository->setClassName(TestPolicy::class);
        //Insert new entity (with child entities)
        $newPolicy = new TestPolicy();
        $newPolicy->policyNo = 'New!';
        $newPolicy->underwriter = $this->objectRepository->getObjectReference(TestUnderwriter::class, ['id' => 1]);
        $newPolicy->effectiveStartDateTime = new \DateTime('today');
        $newPolicy->vehicle = new TestVehicle();
        $newPolicy->vehicle->policy = $newPolicy;
        $newPolicy->vehicle->regNo = 'NEW123';
        $newPolicy->contact = new TestContact();
        $newPolicy->contact->firstName = 'Frederick';
        $newPolicy->contact->lastName = 'Bloggs';
        $insertCount = $this->objectRepository->saveEntity($newPolicy);
        $this->assertEquals(3, $insertCount);
        $this->assertGreaterThan(0, $newPolicy->id);
        $refreshedNewPolicy = $this->objectRepository->find($newPolicy->id);
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
        $newPolicy2->contact = $this->objectRepository->getObjectReference(TestContact::class, ['id' => $contact->id]);
        $newPolicy2->vehicle = new TestVehicle();
        $newPolicy2->vehicle->policy = new ObjectReference($newPolicy2);
        $this->objectRepository->saveEntity($newPolicy2);
        $newPolicy2Id = $newPolicy2->id;

        //Verify save
        $this->objectRepository->clearCache(); //Necessary to force refresh from database as our contact is just an object reference
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
        $this->objectRepository->saveEntity($newPolicy2a);
        $newPolicy2aId = $newPolicy2a->id;

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
        $this->assertEquals(false, $refreshedPolicy2->loginId);
    }

    protected function doInsertTestsOneToMany()
    {
        //Insert children on a one-to-many relationship (new parent, new children)
        $this->objectRepository->setClassName(TestParent::class);
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
//        $this->assertEquals(4375, $refreshedParent->totalWeightOfPets);

        //Update child on a one-to-many relationship
        $refreshedParent->getPets()[0]->weightInGrams += 50;
        $this->objectRepository->saveEntity($refreshedParent);
        $refreshedParent2 = $this->objectRepository->find($parent->getId());
        $this->assertEquals(675, $refreshedParent2->getPets()[0]->weightInGrams);
//        $this->assertEquals(4425, $refreshedParent2->totalWeightOfPets);

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
//        $this->assertEquals(5146, $refreshedParent3->totalWeightOfPets);
        $names = array_column((array) $refreshedParent3->getPets(), 'name');
        $this->assertContains('Fifi', $names);
        $this->assertContains('Nugget', $names);
        $this->assertContains('Snuff', $names);

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
        $newParent->getPets()->append($newPetA);
        $newParent->getPets()->append($newPetB);
        $this->objectRepository->saveEntity($newParent);
        $newParentId = $newParent->getId();
        $refreshedParent4 = $this->objectRepository->find($newParentId);
        $this->assertEquals(2, count($refreshedParent4->getPets()));
        $this->assertEquals('Arnie', $refreshedParent4->getPets()[0]->name);
        $this->assertEquals(9855, $refreshedParent4->getPets()[1]->weightInGrams);
    }
    
    protected function doMultipleInsertTests()
    {
        $this->objectRepository->setClassName(TestPolicy::class);
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
        $this->objectRepository->saveEntities($policies);
        $this->assertGreaterThan(0, $policy1->id);
        $this->assertGreaterThan(0, $policy2->id);
        $this->assertGreaterThan(0, $policy3->id);
    }
    
    protected function doClassInstanceUpdateTests()
    {
        $this->setUp(); //Forget about anything added by previous tests
                
        //Save data to two different instances of the same class
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $parent->getUser()->setType('branch2');
        $parent->getChild()->getUser()->setType('staff2');
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find(1);
        $parentUserId = $refreshedParent->getUser()->getId();
        $childUserId = $refreshedParent->getChild()->getUser()->getId();
        $this->assertNotEquals($parentUserId, $childUserId);
        $this->assertEquals('branch2', $refreshedParent->getUser()->getType());
        $this->assertEquals('staff2', $refreshedParent->getChild()->getUser()->getType());
    }
    
    protected function doEmbeddedUpdateTests()
    {
        //Update a property on an embedded value object
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
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
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $parent->getAddress()->setCountryDescription('Mos Eisley');
        $this->objectRepository->saveEntity($parent);
        $this->objectRepository->clearCache(); //Our change will not have been saved
        $unrefreshedParent = $this->objectRepository->find(1);
        $this->assertEquals('United Kingdom', $unrefreshedParent->getAddress()->getCountryDescription());
        $parent->getAddress()->setCountryCode('EU');
        $this->objectRepository->saveEntity($parent);
        $this->objectRepository->clearCache(); //Change not saved
        $refreshedParent = $this->objectRepository->find(1);
        $this->assertEquals('Somewhere in Europe', $refreshedParent->getAddress()->getCountryDescription());

        //Override default readOnly behaviour
        $this->objectRepository->setEntityConfigOption(
            TestAddress::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['countryDescription' => ['isReadOnly' => false]]
        );

        //Update scalar join value (keep code the same)
        $refreshedParent->getAddress()->setCountryDescription('Mos Eisley');
        $this->objectRepository->saveEntity($refreshedParent);
        $refreshedParent2 = $this->objectRepository->find(1);
        $this->assertEquals('EU', $refreshedParent2->getAddress()->getCountryCode());
        $this->assertEquals('Mos Eisley', $refreshedParent2->getAddress()->getCountryDescription());

        //Insert new scalar join value (NOT YET SUPPORTED)
//        $refreshedParent2->getAddress()->setCountryCode('ZZ');
//        $refreshedParent2->getAddress()->setCountryDescription('Middle Earth');
//        $this->objectRepository->saveEntity($refreshedParent2, false);
//        $refreshedParent3 = $this->objectRepository->find(1);
//        $this->assertEquals('ZZ', $refreshedParent3->getAddress()->getCountryCode());
//        $this->assertEquals('Middle Earth', $refreshedParent3->getAddress()->getCountryDescription());
    }
    
    protected function doScalarJoinTests()
    {
        $this->objectRepository->setClassName(TestParent::class);
        //Revert to default readOnly behaviour
        $this->objectRepository->setEntityConfigOption(
            TestAddress::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['countryDescription' => ['isReadOnly' => null]]
        );

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
        $newChild->address->setCountryCode('US');

        $newParent->setName('Arthur Dent');
        $newParent->setChild($newChild);
        $newParent->setAddress($newAddress);
        $this->objectRepository->saveEntity($newParent);
        $newParentId = $newParent->getId();
        $this->assertGreaterThan(0, $newParentId);
        $this->objectRepository->clearCache(); //Change not saved
        $refreshedNewParent = $this->objectRepository->find($newParentId);
        $this->assertEquals('Deepest Darkest Peru', $refreshedNewParent->getAddress()->getCountryDescription());
        $this->assertEquals('US', $refreshedNewParent->getChild()->address->getCountryCode());

//        //Insert new entity with new scalar join value (NOT YET SUPPORTED)
//        $newAddress2 = new TestAddress();
//        $newAddress2->setTown('Chipping Sodbury');
//        $newAddress2->setCountryCode('YY');
//        $newAddress2->setCountryDescription('Absurdistan');
//        $newParent2 = new TestParent();
//        $newParent2->setAddress($newAddress2);
//        $newParent2Id = $this->objectRepository->saveEntity($newParent2);
//        $refreshedNewParent2 = $this->objectRepository->find($newParent2Id);
//        $this->assertEquals('YY', $refreshedNewParent2->getAddress()->getCountryCode());
//
//        //By default, the description won't save due to the safe default read-only behaviour
//        $this->assertEquals(null, $refreshedNewParent2->getAddress()->getCountryDescription());
//        //Set read only to false, so we can save the description
//        $this->objectRepository->setEntityConfigOption(
//            TestAddress::class,
//            ConfigEntity::COLUMN_OVERRIDES,
//            ['countryDescription' => ['isReadOnly' => false]]
//        );
//        $newParent3 = new TestParent();
//        $newParent3->setAddress($newAddress2);
//        $newChild3 = new TestChild();
//        $newChild3->address = new TestAddress();
//        $newParent3->setChild($newChild3);
//        $newParent3->getChild()->address->setCountryCode('CV');
//        $newParent3->getChild()->address->setCountryDescription('Cabo Verde'); //Scalar join on embedded object with column prefix
//        $newParent3Id = $this->objectRepository->saveEntity($newParent3);
//        $refreshedNewParent3 = $this->objectRepository->find($newParent3Id);
//        $this->assertEquals('Absurdistan', $refreshedNewParent3->getAddress()->getCountryDescription());
//        $this->assertEquals('Cabo Verde', $refreshedNewParent3->getChild()->address->getCountryDescription());
//
        //Override readOnly behaviour for non-scalar-join properties
        $this->objectRepository->setEntityConfigOption(
            TestAddress::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['countryDescription' => ['isReadOnly' => null]]
        );
        $this->objectRepository->setEntityConfigOption(
            TestChild::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['height' => ['isReadOnly' => false]]
        );
        $refreshedNewParent->getChild()->setHeight('48');

        $this->objectRepository->saveEntity($refreshedNewParent);
        $refreshedNewParent4 = $this->objectRepository->find($refreshedNewParent->getId());
        $this->assertEquals(48, $refreshedNewParent4->getChild()->getHeight());
        $this->objectRepository->setEntityConfigOption(
            TestChild::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['height' => ['isReadOnly' => true]]
        );
        $refreshedNewParent4->getChild()->setHeight(49);
        $this->objectRepository->saveEntity($refreshedNewParent4);
        $this->objectRepository->clearCache(); //Change not saved
        $refreshedNewParent5 = $this->objectRepository->find($refreshedNewParent4->getId());
        $this->assertEquals(48, $refreshedNewParent5->getChild()->getHeight());
        $this->objectRepository->setEntityConfigOption(
            TestChild::class,
            ConfigEntity::COLUMN_OVERRIDES,
            ['height' => ['isReadOnly' => null]]
        );

        //Check that new mapping properties work on normal relationship joins (not just scalar joins)
        $this->objectRepository->setEntityConfigOption(
            TestChild::class,
            ConfigEntity::RELATIONSHIP_OVERRIDES,
            [
                'user' => [
                    'joinTable' => 'objectiphy_test.user_alternative',
                    'sourceJoinColumn' => 'user_id',
                    'targetJoinColumn' => 'id'
                ]
            ]
        );
        $altParent = $this->objectRepository->find(1);
        $this->assertEquals('alternative2@example.com', $altParent->getChild()->getUser()->getEmail());
    }

    protected function doMappingOverrideTests()
    {
        $this->objectRepository->resetConfiguration();
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $this->assertEquals('Penfold', $parent->getChild()->name);
        $this->assertEquals(12, $parent->getChild()->height);
        $this->assertEquals('penfold.hamster@example.com', $parent->getChild()->getUser()->getEmail());
        $parent->getChild()->height = 14;
        $this->objectRepository->saveEntity($parent);
        $refreshedParent = $this->objectRepository->find(1);
        $this->assertEquals('penfold.hamster@example.com', $refreshedParent->getChild()->getUser()->getEmail());
        $this->assertEquals(14, $refreshedParent->getChild()->height);

        $this->objectRepository->setConfigOption(ConfigOptions::MAPPING_DIRECTORY, __DIR__ . '/../MappingFiles');
        $refreshedParent2 = $this->objectRepository->find(1);
        $this->assertEquals(14, $refreshedParent2->getChild()->height);
        $this->assertEquals('alternative2@example.com', $refreshedParent2->getChild()->getUser()->getEmail());
        $refreshedParent2->getChild()->height = 13;
        $this->objectRepository->saveEntity($refreshedParent2);
        $this->objectRepository->clearCache();
        $refreshedParent3 = $this->objectRepository->find(1);
        $this->assertEquals(14, $refreshedParent3->getChild()->height);
        $this->assertEquals('alternative2@example.com', $refreshedParent3->getChild()->getUser()->getEmail());
    }

    protected function doDataMapTests()
    {
        $this->objectRepository->resetConfiguration();
        $this->objectRepository->setClassName(TestPolicy::class);
        $this->objectRepository->setEntityConfigOption(
            TestPolicy::class,
            ConfigEntity::COLUMN_OVERRIDES, [
                "policyNo" => [
                    "dataMap" => [
                        "P123456" => "Overridden Policy Number 1",
                        "P123458" => ["operator" => "=", "value" => "Overridden Policy Number 2"],
                        "ELSE" => "Still overridden!"
                    ]
                ]
            ]
        );

        $policy = $this->objectRepository->find(19071974);
        $policy->policyNo = 'Overridden Policy Number 2';
        $this->objectRepository->saveEntity($policy);
        $policy2 = $this->objectRepository->find(19071975);
        $policy2->policyNo = 'Overridden Policy Number 1';
        $this->objectRepository->saveEntity($policy2);
        $policy3 = $this->objectRepository->find(19071978);
        $policy3->policyNo = 'Still overridden!';
        $this->objectRepository->saveEntity($policy3); //Treated as read-only, because we are in an ELSE clause

        $this->objectRepository->resetConfiguration();
        $refreshedPolicy = $this->objectRepository->find(19071974);
        $this->assertEquals('P123458', $refreshedPolicy->policyNo);
        $refreshedPolicy2 = $this->objectRepository->find(19071975);
        $this->assertEquals('P123456', $refreshedPolicy2->policyNo);
        $refreshedPolicy3 = $this->objectRepository->find(19071978);
        $this->assertEquals('P123461', $refreshedPolicy3->policyNo);
    }

    protected function doEmbeddedValueObjectTests()
    {
        $this->objectRepository->setClassName(TestParent::class);
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
        $this->objectRepository->setClassName(TestParent::class);
        //When saving an entity that was only partially loaded (due to serialization groups), do not try to save the unhydrated properties
        $this->objectRepository->setConfigOption(ConfigOptions::SERIALIZATION_GROUPS, ['Default', 'Full']); //Not 'Special', which is used on the child name property

        /** @var $parent TestParent */
        $parent = $this->objectRepository->find(1);
        $this->assertEquals(null, $parent->getChild()->getName()); //We did not hydrate the child name
        $parent->setName('Updated Parent Name!');
        $this->objectRepository->saveEntity($parent);

        $this->objectRepository->setConfigOption(ConfigOptions::SERIALIZATION_GROUPS, []);
        /** @var $updatedParent TestParent */
        $updatedParent = $this->objectRepository->find(1);
        $this->assertEquals('Updated Parent Name!', $updatedParent->getName()); //Parent name update successful
        $this->assertNotEquals(null, $updatedParent->getChild()); //Child was not lost
        $this->assertEquals('Penfold', $updatedParent->getChild()->getName()); //Child name was not lost
    }

    protected function doUnidirectionalScalarRelationshipTests()
    {
        $this->objectRepository->clearCache();
        $this->objectRepository->setClassName(TestPolicy::class);
        $policy = $this->objectRepository->find(19071974);
        $this->assertEquals(1, $policy->contact->employee->id);
        $this->assertEquals(123, $policy->contact->employee->contactId);

        $newEmployee = new TestEmployee();
        $newEmployee->name = 'Carruthers';
        $newEmployee->contactId = $policy->contact->id;
        $policy->contact->employee = $newEmployee;

        //TODO: Consider whether we should try to save grandchildren if children have not changed (ie. save $policy here)
        $this->objectRepository->saveEntity($policy->contact);
        $this->objectRepository->clearCache();
        $updatedPolicy = $this->objectRepository->find(19071974);
        $this->assertGreaterThan(1, $policy->contact->employee->id);
        $this->assertEquals(123, $policy->contact->employee->contactId);
    }
}
