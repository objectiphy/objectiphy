<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Tests\Entity\TestCollection;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestEmployee;
use Objectiphy\Objectiphy\Tests\Entity\TestParentCustomRepo;
use Objectiphy\Objectiphy\Tests\Entity\TestPet;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestSecurityPass;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Repository\CustomRepository;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;
use Objectiphy\Objectiphy\Query\Pagination;
use Objectiphy\Objectiphy\Query\QB;

class CriteriaReadingTest extends IntegrationTestBase
{
    /**
     * Criteria reading using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testReadingDefault()
    {
        $this->testName = 'Criteria Reading Default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Criteria reading, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testReadingMixed()
    {
        $this->testName = 'Criteria Reading Mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);

        $this->doTests();
    }

    /**
     * Criteria reading, overriding annotations to always lazy load everything possible
     */
    public function testReadingLazy()
    {
        $this->testName = 'Criteria Reading Lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);

        $this->doTests();
    }

    /**
     * Criteria reading, overriding annotations to always eager load everything
     */
    public function testReadingEager()
    {
        $this->testName = 'Criteria Reading Eager' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    //Repeat with cache turned off

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

    protected function doTests()
    {
        $this->doReadingTests();
        $this->doOperatorTests();
        $this->doSerializationGroupTests();
        $this->doAdvancedReadingTests();
        $this->doNestedObjectTests();
        $this->doCustomQueryTests();
        $this->doAggregateFunctionTests();
        $this->doSqlTests();
    }

    protected function doReadingTests()
    {
        //Ordering, pagination, and more complex criteria are possible. You can even order
        //by properties on child objects (not possible in Doctrine).
        $this->objectRepository->setOrderBy(['contact.lastName' => 'DESC', 'policyNo']);
        $pagination = new Pagination(7, 1);
        $this->objectRepository->setPagination($pagination);
        $decemberDateRangeCriteria = [
            'effectiveStartDateTime' =>
                ['operator' => 'BETWEEN', 'value' => '2018-12-01', 'value2' => '2018-12-31']
        ];

        $policiesForDecember = $this->objectRepository->findBy($decemberDateRangeCriteria);
//        $latestPoliciesForDecember = $this->objectRepository->findLatestBy($decemberDateRangeCriteria);
//        $policyCount = $this->objectRepository->countBy($decemberDateRangeCriteria);
//        $latestCount = $this->objectRepository->countLatestBy($decemberDateRangeCriteria);
//        $this->assertEquals(37, $policyCount);
//        $this->assertEquals(34, $latestCount);
        $this->assertEquals(7, count($policiesForDecember));
        $this->assertEquals(18, $policiesForDecember[0]->loginId);
        $this->assertEquals(2, $policiesForDecember[6]->loginId);
//        $this->assertEquals(20, count($latestPoliciesForDecember));
        
        $query = QB::create()
            ->where('contact.lastName', QB::EQUALS, ":lastname_alias")
            ->orStart()
                ->where('status', QB::EQUALS, "PAID")
                ->and('effectiveStartDateTime', QB::GREATER_THAN, new \DateTime('2018-12-15'))
            ->orEnd()
            ->or('id', '=', 19072010)
            ->orderBy(['id'])
            ->buildSelectQuery(['lastname_alias' => 'Skywalker']);

//        $this->assertEquals(6, $this->objectRepository->countBy($query));
        $policies3 = $this->objectRepository->findBy($query, null, null, null, 'vehicle.id');
        $this->assertEquals(6, count($policies3));
        $this->assertEquals(1, array_keys($policies3)[1]);
        $this->assertEquals(34, array_keys($policies3)[3]);
        $this->assertNotSame(null, $policies3[37]->vehicle->abiCode); //37 is the vehicle ID

        //Index by a column name
        $policies4 = $this->objectRepository->findBy($query, null, null, null, '`obj_alias_vehicle`.`policy_id`');
        $this->assertEquals(6, count($policies4));
        $this->assertEquals(19071974, array_keys($policies4)[1]);
        $this->assertEquals(19072007, array_keys($policies4)[3]);

        //Index by with no results (do not error!)
        $policies5 = $this->objectRepository->findBy(['id' => 'non-existent'], null, null, null, 'vehicle.id');
        $this->assertEquals(0, count($policies5));
    }

    protected function doOperatorTests()
    {
        //Read using LIKE, paginated
        $this->objectRepository->setClassName(TestPolicy::class);
        $pagination = new Pagination(2);
        $this->objectRepository->setPagination($pagination);
        $refreshedPolicies = $this->objectRepository->findBy([
            'vehicle.makeDesc' => [
                'operator' => 'LIKE',
                'value' => 'F%'
            ]
        ]);
        $this->assertEquals(2, count($refreshedPolicies));
        $this->assertEquals(9, $pagination->getTotalRecords());
        $this->objectRepository->setPagination(null);

        //Read using IN
        $policiesIn = $this->objectRepository->findBy([
            'policyNo' => [
                'operator' => 'IN',
                'value' => ['P123465', 'P123489', 'XXXXXXX']
            ]
        ]);
        $this->assertEquals(2, count($policiesIn));
        $policiesNotIn = $this->objectRepository->findBy([
            'status' => [
                'operator' => 'NOT IN',
                'value' => ['UNPAID', 'VOID', '']
            ]
        ]);
        $this->assertGreaterThan(0, count($policiesNotIn));

        //Read using IS NULL (also have to limit to certain contacts to reduce memory usage when not caching)
        $policiesIsNull = $this->objectRepository->findBy(['modification' => ['operator' => 'IS', 'value' => null], 'contact' => ['operator' => '>', 'value' => 160]]);
        $this->assertEquals(5, count($policiesIsNull));
        $policiesNotIsNull = $this->objectRepository->findBy(['modification' => ['operator' => 'IS NOT', 'value' => null]]);
        $this->assertEquals(1, count($policiesNotIsNull));

        //Use IN operator for ON criteria
        $query = QB::create()->innerJoin(TestContact::class, 'c')
            ->on('c.lastName', QB::IN, ['Skywalker', 'Smith'])
            ->where('contact.lastName', '=', 'c.lastName')
            ->buildSelectQuery();
        $policies = $this->objectRepository->executeQuery($query);
        foreach ($policies as $policy) {
            $this->assertTrue(in_array($policy->contact->lastName, ['Skywalker', 'Smith']));
        }

        //Filter based on properties of one-to-many child object
        $this->objectRepository->setClassName(TestParent::class);
        $parentsWithADog = $this->objectRepository->findBy(['pets.type' => 'dog']);
        $this->assertEquals(1, count($parentsWithADog));
        $this->assertEquals(1, $parentsWithADog[0]->getId());

        //Use indexBy on a one-to-many association
        $this->objectRepository->setClassName(TestParentCustomRepo::class);
        $parent = $this->objectRepository->find(2);
        foreach ($parent->getPets() as $name => $pet) {
            $this->assertIsString($name);
            $this->assertInstanceOf(TestPet::class, $pet);
        }
        $child = $parent->child;
    }

    protected function doSerializationGroupTests()
    {
        $this->objectRepository->setClassName(TestPolicy::class);
        $this->objectRepository->setPagination(null);
        $this->objectRepository->setConfigOption(ConfigOptions::SERIALIZATION_GROUPS, ['Default', 'PolicyDetails']);
        $this->objectRepository->setConfigOption(ConfigOptions::HYDRATE_UNGROUPED_PROPERTIES, false);

        $criteria['policyNo'] = ['operator' => 'LIKE', 'value' => 'P1234%'];
        $policies = $this->objectRepository->findBy($criteria);
        $this->assertEquals(38, count($policies));
        $this->assertNotEmpty($policies[0]->id);
        $this->assertNotEmpty($policies[0]->underwriter->id);
        $this->assertEmpty($policies[0]->contact);
        $this->assertEmpty($policies[0]->status);

        $this->objectRepository->setClassName(TestParent::class);
        $this->objectRepository->setConfigOption(ConfigOptions::SERIALIZATION_GROUPS, ['Default']);
        $parent = $this->objectRepository->find(1);
        //Only things populated: id, name, user
        $this->assertNotNull($parent->getId());
        $this->assertNotNull($parent->getName());
        $this->assertNotNull($parent->getUser());
        $this->assertNull($parent->getChild());
        $this->assertNull($parent->getAddress());
        $this->assertNull($parent->getPets());

        $this->objectRepository->setConfigOption(ConfigOptions::HYDRATE_UNGROUPED_PROPERTIES, true);
        $this->objectRepository->setClassName(TestPolicy::class);
        $query = QB::create()->where('contact.lastName', '=', 'Skywalker')
            ->orStart()
                ->where('status', '=', 'PAID')
                ->and('effectiveStartDateTime', '>', new \DateTime('2018-12-15'))
            ->orEnd()
            ->or('id', '=', 19072010)
            ->buildSelectQuery();
        $policies3a = $this->objectRepository->findBy($query, null, null, null, 'vehicle.id');
        $this->assertEquals('', $policies3a[20]->contact->postcode);
        $this->objectRepository->setConfigOption(ConfigOptions::SERIALIZATION_GROUPS, []);
    }

    protected function doAdvancedReadingTests()
    {
        //Load an object that contains two different instances of the same class
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $this->assertEquals(TestCollection::class, get_class($parent->pets));

        $this->assertNotEquals($parent->getUser()->getId(), $parent->getChild()->getUser()->getId());
        $this->assertEquals('branch', $parent->getUser()->getType());
        $this->assertEquals('staff', $parent->getChild()->getUser()->getType());
        $this->assertEquals(4, count($parent->getPets()));
        $this->assertEquals('Flame', $parent->getPets()[0]->name);

        //Check that embedded value object was loaded
        $this->assertEquals('London', $parent->getAddress()->getTown());

        //And that scalar join on embedded value object was also loaded
        $this->assertEquals('United Kingdom', $parent->getAddress()->getCountryDescription());

        //Load using property of value object as criteria
        $parentLoadedByAddress = $this->objectRepository->findOneBy(['address.town' => 'London']);
        $this->assertEquals('London', $parentLoadedByAddress->getAddress()->getTown());

        //Load using scalar join value as criteria
        $parentLoadByCountry = $this->objectRepository->findOneBy(['address.countryDescription' => 'United Kingdom']);
        $this->assertEquals('GB', $parentLoadByCountry->getAddress()->getCountryCode());

        //Load using deeply nested property of value object as criteria
        $maxDepth = $this->objectRepository->getConfiguration()->maxDepth;
        if ($maxDepth > 2) {
            $nestedParentLoadedByAddress = $this->objectRepository->findOneBy(
                ['child.parent.address.town' => 'London']
            );
            $this->assertEquals('London', $nestedParentLoadedByAddress->getAddress()->getTown());
            $nestedParentLoadedByCountry = $this->objectRepository->findOneBy(
                ['child.parent.address.countryDescription' => 'Deepest Darkest Peru']
            );
            $this->assertEquals('Eleanor Shellstrop', $nestedParentLoadedByCountry->getName());
        }
        //Load using prefixed column of value object as criteria
        $prefixedColumnCriteriaParent = $this->objectRepository->findOneBy(['child.address.town'=>'Loughborough']);
        $this->assertEquals(2, $prefixedColumnCriteriaParent->getId());

        //Aggregate function for a property value
//        $parentTwo = $this->objectRepository->find(2);
//        $this->assertEquals(7, $parentTwo->numberOfPets);
//        $this->assertEquals(220, $parentTwo->totalWeightOfPets);
    }

    protected function doNestedObjectTests()
    {
        //Test model is made up of many different instances of the same class, eg.
        //Employee1->Mentor1->Mentor2->Mentee1->Mentee2 (all are Employee objects)
        $this->objectRepository->setClassName(TestEmployee::class);
        $employeeJack = $this->objectRepository->findOneBy(['name' => 'Jack']);
        $this->assertEquals(4, $employeeJack->id);
        $this->assertEquals('Tim', $employeeJack->mentor->name);
        $this->assertEquals(1, $employeeJack->mentor->mentor->id);
        $this->assertEquals('Becky', $employeeJack->mentor->mentor->mentor->name);
        $this->assertEquals('Becky', $employeeJack->unionRep->name);
        $this->assertEquals('Becky', $employeeJack->unionRep->unionRep->name);
        $this->assertEquals(4, count($employeeJack->unionRep->unionMembers));

        $employeeRob = $this->objectRepository->findOneBy(['name' => 'Rob']);
        $this->assertEquals(3, count($employeeRob->unionMembers));
        $this->assertEquals('Tim', $employeeRob->mentee->mentee->mentee->name);

        //Check that multiple scalar joins on an embedded value object on a child object are hydrated
        $this->objectRepository->setClassName(TestSecurityPass::class);
        $securityPass = $this->objectRepository->findOneBy(['serialNo' => '1234567']);
        $this->assertEquals('SB', $securityPass->employee->position->positionKey);
        $this->assertEquals('Supreme Being', $securityPass->employee->position->positionValue);
        $this->assertEquals('Lead PHP Developer and master of the black arts', $securityPass->employee->position->positionDescription);
        $this->assertEquals('Tea boy', $securityPass->employee->mentee->position->positionDescription);
        $this->assertEquals(2, $securityPass->employee->mentee->unionRep->id);
    }

    protected function doCustomQueryTests()
    {
        //Find with a custom repository
        $repositoryFactory = new RepositoryFactory($this->pdo);
        /** @var CustomRepository $customRepository */
        $customRepository = $repositoryFactory->createRepository(TestParent::class, CustomRepository::class);
        $parent = $customRepository->find(2);
        $this->assertEquals('Loaded with custom repo!', $parent->getName());

        try {
            $repositoryFactory->createRepository(TestParent::class, 'MadeupRepositoryClassName');
            $this->assertEquals(false, true); //Should never hit this!
        } catch (ObjectiphyException $ex) {
            $this->assertStringContainsString('does not exist', $ex->getMessage());
        }
    }

    protected function doAggregateFunctionTests()
    {
//        //Use criteria that references an aggregate function property value
//        $this->objectRepository->setClassName(TestParent::class);
//        $criteria = QB::create()->where('totalWeightOfPets', '>', 1000)->build();
//        $heavilyPettedParentsLol = $this->objectRepository->findBy($criteria);
//        $this->assertEquals(2, count($heavilyPettedParentsLol));
//        $this->assertEquals(1, $heavilyPettedParentsLol[0]->getId());
//
//        //Use criteria that uses an aggregate function directly where we would not normally join
//        $this->objectRepository->seClassName(TestPolicy::class);
//        $criteria = QB::create()->where('vehicle.wheels.id', '>', 4, null, 'COUNT')->build(); //(5 wheels incl steering wheel!)
//        $policiesWithFiveWheelVehicle = $this->objectRepository->findBy($criteria);
//        $this->assertEquals(1, count($policiesWithFiveWheelVehicle));
//        $this->assertEquals(1, $policiesWithFiveWheelVehicle[0]->vehicle->id);
//
//        //Order by aggregate function property (case insensitive direction)
//        $this->objectRepository->setClassName(TestParent::class);
//        $parents = $this->objectRepository->findAll(['totalWeightOfPets'=>'desc']);
//        $this->assertEquals(1, $parents[0]->getId());
//
//        //Use criteria with an aggregate function where we already join for an aggregate function property
//        //NOTE: Keep this as the last test here, so that the doSqlTests method, below, gets what it expects!
//        $this->objectRepository->setClassName(TestParent::class);
//        $criteria = QB::create()->where('pets.id', QB::LTE, 4, null, 'COUNT')->build();
//        $parentsWithLessThanFivePets = $this->objectRepository->findBy($criteria);
//        $this->assertEquals(2, count($parentsWithLessThanFivePets));
//        $this->assertEquals('Danger Mouse', $parentsWithLessThanFivePets[0]->getName());
    }

    protected function doSqlTests()
    {
//        if ($this->testName != 'Criteria Reading eager') { //Final query will be different for eager load
//            $sql = $this->objectRepository->getQuery(false);
//            $this->assertStringEndsWith("(COUNT(`objectiphy_test`.`pets`.`id`) <= :param_1)", $sql);
//            $parameterisedSql = $this->objectRepository->getQuery();
//            $this->assertStringEndsWith("(COUNT(`objectiphy_test`.`pets`.`id`) <= '4')", $parameterisedSql);
//            $sqlHistory = $this->objectRepository->getQueryHistory();
//            $this->assertGreaterThan(40, count($sqlHistory));
//            $this->assertStringStartsWith('SELECT', reset($sqlHistory));
//            $this->assertStringStartsWith('SELECT', end($sqlHistory));
//            $params = $this->objectRepository->getParamHistory();
//            $this->assertEquals(count($sqlHistory), count($params));
//            $this->assertEquals(['param_1' => 4], end($params));
//            $parameterisedSqlHistory = $this->objectRepository->getQueryHistory(true);
//            $this->assertEquals(count($sqlHistory), count($parameterisedSqlHistory));
//            $this->assertStringEndsWith("(COUNT(`objectiphy_test`.`pets`.`id`) <= '4')", end($parameterisedSqlHistory));
//        }
    }
}
