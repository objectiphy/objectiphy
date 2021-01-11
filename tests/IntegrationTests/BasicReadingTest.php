<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;
use Objectiphy\Objectiphy\Orm\IterableResult;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\QB;
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
        $this->testName = 'Reading default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Basic reading, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testReadingMixed()
    {
        $this->testName = 'Reading mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Basic reading, overriding annotations to always lazy load everything possible
     */
    public function testReadingLazy()
    {
        $this->testName = 'Reading lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Basic reading, overridding annotations to always eager load everything
     */
    public function testReadingEager()
    {
        $this->testName = 'Reading eager' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    public function testReadingExceptions()
    {
        $this->testName = 'Reading exceptions' . $this->getCacheSuffix();
        $this->expectException(QueryException::class);
        $this->objectRepository->findBy(['something invalid'=>'gibberish']);
    }

    //Repeat with the cache turned off
    public function testReadingDefaultNoCache()
    {
        $this->disableCache();
        $this->testReadingDefault();
    }

    public function testReadingMixedNoCache()
    {
        $this->disableCache();
        $this->testReadingMixed();
    }

    public function testReadingLazyNoCache()
    {
        $this->disableCache();
        $this->testReadingLazy();
    }

    public function testReadingEagerNoCache()
    {
        $this->disableCache();
        $this->testReadingEager();
    }

    public function testReadingExceptionsNoCache()
    {
        $this->disableCache();
        $this->testReadingExceptions();
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
        $policy = $this->objectRepository->find(19071974);
        $this->assertEquals('P123456', $policy->policyNo);
        $this->assertEquals('Skywalker', $policy->contact->lastName);
        $this->assertEquals(5, count($policy->vehicle->wheels));
        $this->assertEquals(TestCollection::class, get_class($policy->vehicle->wheels));

        if (!$this->getCacheSuffix()) {
            //Calling find again with the same ID should return the same instance (no db lookup needed)
            $policya = $this->objectRepository->find(19071974);
            $this->assertSame($policy, $policya);
        }

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
//        $latestPolicy = $this->objectRepository->findLatestOneBy(['policyNo' => 'P123458']);
//        $this->assertEquals(19071977, $latestPolicy->id);

        //Load with LIKE (or any other) operator
        $this->objectRepository->setOrderBy(['id' => 'ASC']);
        $criteria = ['policyNo' => ['operator' => 'LIKE', 'value' => 'P1234%']];
        $policies = $this->objectRepository->findBy($criteria);
        $this->assertEquals(38, count($policies));
        $this->assertEquals(19071973, $policies[0]->id);
        
        //Iterable result
        $iterable = $this->objectRepository->findOnDemandBy($criteria);
        $this->assertInstanceOf(IterableResult::class, $iterable);
        foreach ($iterable as $policy) {
            $this->assertInstanceOf(TestPolicy::class, $policy);
            $this->assertEquals('P1234', substr($policy->policyNo, 0, 5));
        }

        //Ensure zero gets interpreted correctly when using array syntax
        $this->objectRepository->setClassName(TestChild::class);
        $criteria = ['height'=>['operator'=>'>', 'value'=>0]];
        $children = $this->objectRepository->findBy($criteria);
        $this->assertEquals(2, count($children));

        $criteria2 = ['height'=>['operator'=>'=', 'value'=>0]];
        $children2 = $this->objectRepository->findBy($criteria2);
        $this->assertEquals(1, count($children2));

        //Weird property and column names
        $this->objectRepository->setClassName(TestWeirdPropertyNames::class);
        $weirdo = $this->objectRepository->find(1);
        $this->assertEquals('Lister', $weirdo->last_name);
        $this->assertEquals('The End', $weirdo->a_VERY_Very_InconsistentnamingConvention_here);
        $this->assertEquals('1988-02-15 21:00:00', $weirdo->some_random_event_dateTime->format('Y-m-d H:i:s'));
        $this->assertEquals('United Kingdom', $weirdo->address_with_underscores->getCountryDescription());
        $this->assertEquals('danger.mouse@example.com', $weirdo->test_user->getEmail());
    }

    protected function doAssumedPkTests()
    {
        $this->objectRepository->setClassName(TestAssumedPk::class);
        $paloma = $this->objectRepository->find(2);
        $this->assertEquals('Paloma Faith', $paloma->name);
        $matt = $this->objectRepository->find(0); //Primary key of zero should still load
        $this->assertEquals('Matt Bellamy', $matt->name);
    }

    protected function doNonPkTests()
    {
        //Join to a child using a non-pk column and ensure child and grandchild load OK
        $this->objectRepository->setClassName(TestParentOfNonPkChild::class);
        $parentOfNonPkChild = $this->objectRepository->find(2);
        $this->assertEquals('Eselbeth', $parentOfNonPkChild->getName());
        $this->assertEquals('Ariadne', $parentOfNonPkChild->getChild()->getNebulousIdentifier());
        $this->assertEquals('penfold.hamster@example.com', $parentOfNonPkChild->getChild()->getUser()->getEmail());

         //Ensure we succeed when using findBy criteria
        $this->objectRepository->setClassName(TestNonPkChild::class);
        $nonPkChild = $this->objectRepository->findOneBy(['nebulousIdentifier'=>'Lambeth']);
        $this->assertEquals(1, $nonPkChild->getParent()->getId());

        //...But that we throw an exception if we try to use find on a class that has no primary key
        try {
            $nonPkChild = $this->objectRepository->find('Lambeth');
        } catch (ObjectiphyException $ex) {
            //Instead of setExpectedException, we try/catch so execution of subsequent tests can continue.
            $this->assertStringContainsString('primary key', $ex->getMessage());
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
        $this->objectRepository->setClassName(TestPolicy::class);

        //Get unbound results
//        $query = QB::create()
//            ->select('vehicle.regNo')
//            ->from(TestPolicy::class)
//            ->where('contact', '=', 123)
//            ->buildSelectQuery();
//        $result = $this->objectRepository->findOneValueBy($query);
//        $this->assertEquals('PJ63LXR', $result);
//
//
////        $regNo = $this->objectRepository->findOneValueBy(['contact' => 123], 'vehicle.regNo');
////        $this->assertEquals('PJ63LXR', $regNo);
////        //Make sure we still get bound results after calling that
////        $policy = $this->objectRepository->findOneBy(['contact' => 123]);
////        $this->assertInstanceOf(TestPolicy::class, $policy);
//        //Get array of scalar values
//        $query2 = QB::create()
//            ->select('policyNo')
//            ->from(TestPolicy::class)
//            ->where('contact.lastName', QB::EQ, 'Skywalker')
//            ->buildSelectQuery();
//        $policyNumbers = $this->objectRepository->findValuesBy($query2, ['policyNo']);
////        $policyNumbers = $this->objectRepository->findValuesBy(['contact.lastName' => 'Skywalker'], 'policyNo',
////            ['policyNo']);
//        $this->assertEquals(2, count($policyNumbers));
//        $this->assertEquals('P123456', $policyNumbers[0]);
//        $this->assertEquals('P123457', $policyNumbers[1]);
//        //Get iterable scalar values
//        $iterablePolicyNumbers = $this->objectRepository->findIterableValuesBy(['contact.lastName' => 'Skywalker'],
//            'policyNo', ['policyNo']);
//        $this->assertInstanceOf(IterableResult::class, $iterablePolicyNumbers);
//        $iterablePolicyNumbers->next();
//        $this->assertEquals('P123456', $iterablePolicyNumbers->current());
//        $iterablePolicyNumbers->next();
//        $this->assertEquals('P123457', $iterablePolicyNumbers->current());
//        $iterablePolicyNumbers->closeConnection();

        //Get some arrays
        $this->objectRepository->setConfigOption('bindToEntities', false);
        $policy = $this->objectRepository->findOneBy(['contact' => 123]);
        $this->assertEquals(123, $policy['contact_id']);
        $this->assertEquals('Skywalker', $policy['contact_lastName']);
        $this->assertEquals('P123456', $policy['policyNo']);
        $this->assertEquals('Vauxhall', $policy['vehicle_makeDesc']);
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
        try {
            $this->objectRepository->setClassName(TestAddress::class);
            $this->objectRepository->findOneBy(['town' => 'London']);
            $this->assertEquals(false, true); //Should never hit this!
        } catch (ObjectiphyException $ex) {
            $this->assertStringContainsString('table mapping', $ex->getMessage());
        }

        $parent = $parentRepository->find(1);
        $pets = $parent->getPets();
        $this->assertEquals('Danger Mouse', $pets[0]->parent->getName());

        //Column prefix on embedded object
        $this->assertEquals('212c Baker Street', $parent->child->address->getLine1());

        //Ensure that proxies use the entity factory
        $vehicleFactory = new TestVehicleFactory();
        $this->objectRepository->setClassName(TestVehicle::class);
        $config = $this->objectRepository->getConfiguration();
        $config->bindToEntities = true;
        $config->eagerLoadToOne = false;
        $config->eagerLoadToMany = false;
        $this->objectRepository->setConfiguration($config);
        $this->objectRepository->setEntityConfigOption(TestVehicle::class, 'entityFactory', $vehicleFactory);
        $vehicle = $this->objectRepository->find(2);
        $this->assertInstanceOf(EntityProxyInterface::class, $vehicle);
        $this->assertEquals('Fiat', $vehicle->makeDesc);
        $this->assertEquals('This has been created by a factory!', $vehicle->factoryTest);
    }
}
