<?php

//TODO:
//Map properties without ambiguity as per documentation
//Respect setGuessMappings(false)
//remove valueAssignments?
//Check why so many levels on mentor/mentee/mentee etc.
//Make sure custom respositories work when specified on table annotations
//Value matching - refactor
//More refactoring

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\EntityProxyInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\IterableResult;
use Objectiphy\Objectiphy\RepositoryFactory;
use Objectiphy\Objectiphy\Tests\Entity\TestAddress;
use Objectiphy\Objectiphy\Tests\Entity\TestAssumedPk;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestCollection;
use Objectiphy\Objectiphy\Tests\Entity\TestNonPkChild;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestParentOfNonPkChild;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;
use Objectiphy\Objectiphy\Tests\Entity\TestWeirdPropertyNames;
use Objectiphy\Objectiphy\Tests\Factory\TestVehicleFactory;

class BasicReadingTest extends IntegrationTestBase
{
    /**
     * Basic reading using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testReadingDefault()
    {
        $this->testName = 'Reading default';
        $this->doTests();
    }

    /**
     * Basic reading, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testReadingMixed()
    {
        $this->testName = 'Reading mixed';
        $this->objectRepository->setEagerLoad(true, false);
        $this->doTests();
    }

    /**
     * Basic reading, overriding annotations to always lazy load everything possible
     */
    public function testReadingLazy()
    {
        $this->testName = 'Reading lazy';
        $this->objectRepository->setEagerLoad(false, false);
        $this->doTests();
    }

    /**
     * Basic reading, overridding annotations to always eager load everything
     */
    public function testReadingEager()
    {
        $this->testName = 'Reading eager';
        $this->objectRepository->setEagerLoad(true, true);
        $this->doTests();
    }

    public function testReadingExceptions()
    {
        $this->testName = 'Reading exceptions';
        $this->expectException(ObjectiphyException::class);
        $this->objectRepository->findBy(['something invalid'=>'gibberish']);
    }

    protected function doTests()
    {
        $this->doReadingTests();
        $this->doAssumedPkTests();
        $this->doNonPkTests();
        $this->doUnboundTests();
        $this->doAdvancedTests();
    }

    protected function doReadingTests()
    {
        //Find by ID, as per doctrine
        $start = microtime(true);
        $policy = $this->objectRepository->find(19071974);
        $time = round(microtime(true) - $start, 3);

        $this->assertEquals('P123456', $policy->policyNo);
        $this->assertEquals('Skywalker', $policy->contact->lastName);
        $this->assertEquals(5, count($policy->vehicle->wheels));
        $v = $policy->telematicsBox->vehicle;

        $this->assertEquals(TestCollection::class, get_class($policy->vehicle->wheels));

        //Find by child property (not possible in Doctrine)
        $policy2 = $this->objectRepository->findOneBy(['vehicle.regNo' => 'PJ63LXR']);
        $this->assertEquals('PJ63LXR', $policy2->vehicle->regNo);

        //Find by child ID, as per doctrine
        $policy3 = $this->objectRepository->findOneBy(['contact' => 123]);
        $this->assertEquals('P123456', $policy3->policyNo);
        $this->assertEquals(123, $policy3->contact->id);

        //Find latest record from transactional table (common property can be specified
        //either by constructor injection or by passing a value in the findLatestBy or
        //findLatestOneBy method)
        $latestPolicy = $this->objectRepository->findLatestOneBy(['policyNo' => 'P123458']);
        $this->assertEquals(19071977, $latestPolicy->id);

        //Load with LIKE (or any other) operator
        $this->objectRepository->setOrderBy(['id' => 'ASC']);
        $criteria = ['policyNo' => ['operator' => 'LIKE', 'value' => 'P1234%']];
        $policies = $this->objectRepository->findBy($criteria);
        $this->assertEquals(38, count($policies));
        $this->assertEquals(19071973, $policies[0]->id);
        
        //Iterable result
        $iterable = $this->objectRepository->findIterableBy($criteria);
        $this->assertInstanceOf(IterableResult::class, $iterable);
        foreach ($iterable as $policy) {
            $this->assertInstanceOf(TestPolicy::class, $policy);
            $this->assertEQuals('P1234', substr($policy->policyNo, 0, 5));
        }

        //Ensure zero gets interpreted correctly when using array syntax
        $this->objectRepository->setEntityClassName(TestChild::class);
        $criteria = ['height'=>['operator'=>'>', 'value'=>0]];
        $children = $this->objectRepository->findBy($criteria);
        $this->assertEquals(2, count($children));

        $criteria2 = ['height'=>['operator'=>'=', 'value'=>0]];
        $children2 = $this->objectRepository->findBy($criteria2);
        $this->assertEquals(1, count($children2));

        //Weird property and column names
        $this->objectRepository->setEntityClassName(TestWeirdPropertyNames::class);
        $weirdo = $this->objectRepository->find(1);
        $this->assertEquals('Lister', $weirdo->last_name);
        $this->assertEquals('The End', $weirdo->a_VERY_Very_InconsistentnamingConvention_here);
        $this->assertEquals('1988-02-15 21:00:00', $weirdo->some_random_event_dateTime->format('Y-m-d H:i:s'));
        $this->assertEquals('United Kingdom', $weirdo->address_with_underscores->getCountryDescription());
        $this->assertEquals('danger.mouse@example.com', $weirdo->test_user->getEmail());
    }

    protected function doAssumedPkTests()
    {
        $this->objectRepository->setEntityClassName(TestAssumedPk::class);
        $paloma = $this->objectRepository->find(2);
        $this->assertEquals('Paloma Faith', $paloma->name);
        $matt = $this->objectRepository->find(0); //Primary key of zero should still load
        $this->assertEquals('Matt Bellamy', $matt->name);
    }

    protected function doNonPkTests()
    {
        //Join to a child using a non-pk column and ensure child and grandchild load OK
        $this->objectRepository->setEntityClassName(TestParentOfNonPkChild::class);
        $parentOfNonPkChild = $this->objectRepository->find(2);
        $this->assertEquals('Eselbeth', $parentOfNonPkChild->getName());
        $this->assertEquals('Ariadne', $parentOfNonPkChild->getChild()->getNebulousIdentifier());
        $this->assertEquals('penfold.hamster@example.com', $parentOfNonPkChild->getChild()->getUser()->getEmail());

         //Ensure we succeed when using findBy criteria
        $this->objectRepository->setEntityClassName(TestNonPkChild::class);
        $nonPkChild = $this->objectRepository->findOneBy(['nebulousIdentifier'=>'Lambeth']);
        $this->assertEquals(1, $nonPkChild->getParent()->getId());

        //...But that we throw an exception if we try to use find on a class that has no primary key
        try {
            $nonPkChild = $this->objectRepository->find('Lambeth');
        } catch (ObjectiphyException $ex) {
            //Instead of setExpectedException, we try/catch so execution of subsequent tests can continue.
            $this->assertContains('primary key', $ex->getMessage());
        }

        //Check we can join on a non-primary foreign key
        $fosterKids = $parentOfNonPkChild->fosterKids;
        $this->assertEquals(2, count($fosterKids));
        $this->assertEquals('Lambeth', $fosterKids[0]->getNebulousIdentifier());
        $this->assertEquals('Ariadne', $fosterKids[1]->getNebulousIdentifier());
        $this->assertEquals('Angus', $fosterKids[0]->getParent()->getName());
        $this->assertEquals('Eselbeth', $fosterKids[0]->fosterParent->getName());
        
        //Check we don't get in a muddle with two properties pointing to the same class type
        $this->assertEquals(1, $parentOfNonPkChild->getSecondChild()->getParent()->getId());
    }

    protected function doUnboundTests()
    {
        //Get unbound results
        $this->objectRepository->setEntityClassName(TestPolicy::class);
        $regNo = $this->objectRepository->findOneValueBy(['contact' => 123], 'vehicle.regNo');
        $this->assertEquals('PJ63LXR', $regNo);
        //Make sure we still get bound results after calling that
        $policy = $this->objectRepository->findOneBy(['contact' => 123]);
        $this->assertInstanceOf(TestPolicy::class, $policy);
        //Get array of scalar values
        $policyNumbers = $this->objectRepository->findValuesBy(['contact.lastName' => 'Skywalker'], 'policyNo',
            ['policyNo']);
        $this->assertEquals(2, count($policyNumbers));
        $this->assertEquals('P123456', $policyNumbers[0]);
        $this->assertEquals('P123457', $policyNumbers[1]);
        //Get iterable scalar values
        $iterablePolicyNumbers = $this->objectRepository->findIterableValuesBy(['contact.lastName' => 'Skywalker'],
            'policyNo', ['policyNo']);
        $this->assertInstanceOf(IterableResult::class, $iterablePolicyNumbers);
        $iterablePolicyNumbers->next();
        $this->assertEquals('P123456', $iterablePolicyNumbers->current());
        $iterablePolicyNumbers->next();
        $this->assertEquals('P123457', $iterablePolicyNumbers->current());
        $iterablePolicyNumbers->closeConnection();

        //Get some arrays
        $this->objectRepository->setBindToEntities(false);
        $policy = $this->objectRepository->findOneBy(['contact' => 123]);
        $this->assertEquals(123, $policy['contact_id']);
        $this->assertEquals('Skywalker', $policy['contact_lastname']);
        $this->assertEquals('P123456', $policy['policyno']);
        $this->assertEquals('Vauxhall', $policy['vehicle_makedesc']);
        $this->objectRepository->setOrderBy(['id']);
        $policies = $this->objectRepository->findBy(['policyNo' => ['operator' => 'LIKE', 'value' => 'P1234%']]);
        $this->assertEquals(38, count($policies));
        $this->assertEquals(19071973, $policies[0]['id']);
    }

    protected function doAdvancedTests()
    {
        //Load a value from a scalar join on an embedded value object
        $repositoryFactory = new RepositoryFactory($this->pdo);
        $parentRepository = $repositoryFactory->createRepository(TestParent::class);
        $parent = $parentRepository->find(1);
        $this->assertEquals('United Kingdom', $parent->address->getCountryDescription());

        //Check error message when trying to load an entity with no table definition
        $this->objectRepository->setEntityClassName(TestAddress::class);
        try {
            $this->objectRepository->findOneBy(['town' => 'London']);
            $this->assertEquals(false, true); //Should never hit this!
        } catch (ObjectiphyException $ex) {
            $this->assertContains('Could not locate table', $ex->getMessage());
        }

        //Make sure we do not try to load parent data from database when lazy loading a child (or children), even if the
        //child has a reference to the parent - as we already know what the parent object is.
        $parent = $parentRepository->find(1);
        $pets = $parent->getPets();
        $query = $parentRepository->getQuery();
        $this->assertNotContains('`objectiphy_test`.`parent`', $query);
        $this->assertEquals('Danger Mouse', $pets[0]->parent->getName());

        //Column prefix on embedded object
        $this->assertEquals('212c Baker Street', $parent->child->address->getLine1());

        //Ensure that proxies use the entity factory
        $vehicleFactory = new TestVehicleFactory();
        $this->objectRepository->setBindToEntities(true);
        $this->objectRepository->setEntityClassName(TestVehicle::class, $vehicleFactory);
        $this->objectRepository->setEagerLoad(false, false);
        $vehicle = $this->objectRepository->find(2);
        $this->assertInstanceOf(EntityProxyInterface::class, $vehicle);
        $this->assertEquals('Fiat', $vehicle->makeDesc);
        $this->assertEquals('This has been created by a factory!', $vehicle->factoryTest);
    }
}
