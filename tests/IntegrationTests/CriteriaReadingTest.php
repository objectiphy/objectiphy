<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Tests\Entity\TestCollection;
use Objectiphy\Objectiphy\Tests\Entity\TestEmployee;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestSecurityPass;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Repository\CustomRepository;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Factory\RepositoryFactory;
use Objectiphy\Objectiphy\Query\Pagination;
use Objectiphy\Objectiphy\Query\QueryBuilder;
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
        $this->testName = 'Critiera Reading default';
        $this->doTests();
    }

    /**
     * Criteria reading, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testReadingMixed()
    {
        $this->testName = 'Criteria Reading mixed';
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);

        $this->doTests();
    }

    /**
     * Criteria reading, overriding annotations to always lazy load everything possible
     */
    public function testReadingLazy()
    {
        $this->testName = 'Criteria Reading lazy';
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);

        $this->doTests();
    }

    /**
     * Criteria reading, overriding annotations to always eager load everything
     */
    public function testReadingEager()
    {
        $this->testName = 'Criteria Reading eager';
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    protected function doTests()
    {
        $this->doReadingTests();
        $this->doSerializationGroupTests();
        $this->doOperatorTests();
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
        $pagination = new Pagination(20, 1);
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
        $this->assertEquals(20, count($policiesForDecember));
//        $this->assertEquals(20, count($latestPoliciesForDecember));

        //Load using criteria from a criteria builder.
        $params = [
            'lastname' => 'McG',
            'postcode' => 'PO27AJ',
            'some_random_value' => 'this_will_be_ignored'
        ]; // these could be grabbed from request object
//        $QueryBuilder = new QueryBuilder();
//
//        $QueryBuilder->addBeginsWith('contact.lastName', 'lastname');
//        $QueryBuilder->addExpression('=', 'contact.postcode', 'postcode');

//        //Alternative syntax:
//        $QueryBuilder->addExpressions([
//            ['beginsWith', 'contact.lastName', 'lastname'],
//            ['=', 'contact.postcode', 'postcode'],
//            ['contains', 'login.email', 'email'],
//        ]);

        //Use serialization groups, CriteriaExpressions with nested OR, and key by property.
//        $this->objectRepository->clearSerializationGroups();
//        $this->objectRepository->addSerializationGroups(['Default', 'PolicyDetails']);
//        $criteria = $QueryBuilder->buildCriteria($params);
//        $criteria['policyNo'] = ['operator' => 'LIKE', 'value' => 'P1234%'];
//        $policies2 = $this->objectRepository->findBy($criteria);
//        $this->assertEquals(1, count($policies2));
//        $this->assertEquals(19071988, $policies2[0]->id);
//        $this->assertNotEmpty($policies2[0]->underwriter->id);

//        $expression = (new CriteriaExpression(new FieldExpression('contact.lastName'), null, '=', 'Skywalker'))
//            ->or(
//                (new CriteriaExpression(new FieldExpression('status'), null, '=', 'PAID'))
//                    ->and(['effectiveStartDateTime', null, '>', new \DateTime('2018-12-15')])
//            )
//            ->or(['id', null, '=', 19072010]);

        //Nicer syntax, same result...  (operators can be strings or you can use the class constants)
//        $newCriteria = QB::create()
//            ->where('contact.lastName', QB::EQUALS, ":lastname_alias")
//            ->orWhere('status', QB::EQUALS, "PAID",
//                QB::create()->andWhere('effectiveStartDateTime', QB::GREATER_THAN, new \DateTime('2018-12-15')))
//            ->orWhere('id', '=', 19072010)
//            ->build(['lastname_alias' => 'Skywalker']);
        
        $query = QB::create()
            ->where('contact.lastName', QB::EQUALS, ":lastname_alias")
            ->orStart()
                ->where('status', QB::EQUALS, "PAID")
                ->and('effectiveStartDateTime', QB::GREATER_THAN, new \DateTime('2018-12-15'))
            ->orEnd()
            ->or('id', '=', 19072010)
            ->orderBy(['id'])
            ->buildSelectQuery(['lastname_alias' => 'Skywalker']);
        
        //$this->assertEquals([$expression], $newCriteria);

//        $this->assertEquals(6, $this->objectRepository->countBy([$expression]));
        //$this->objectRepository->setOrderBy(['id']);
        $policies3 = $this->objectRepository->findBy($query, null, null, null, 'vehicle.id');

        $this->assertEquals(6, count($policies3));
        $this->assertEquals(1, array_keys($policies3)[1]);
        $this->assertEquals(34, array_keys($policies3)[3]);
        $this->assertNotSame(null, $policies3[37]->vehicle->abiCode); //37 is the vehicle ID
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
        $this->assertEquals(10, $pagination->getTotalRecords());
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

        //Read using IS NULL
        $policiesIsNull = $this->objectRepository->findBy(['modification' => ['operator' => 'IS', 'value' => null]]);
        $this->assertEquals(43, count($policiesIsNull));
        $policiesNotIsNull = $this->objectRepository->findBy(['modification' => ['operator' => 'IS NOT', 'value' => null]]);
        $this->assertEquals(1, count($policiesNotIsNull));

        //Filter based on properties of one-to-many child object
        $this->objectRepository->setClassName(TestParent::class);
        $parentsWithADog = $this->objectRepository->findBy(['pets.type' => 'dog']);
        $this->assertEquals(1, count($parentsWithADog));
        $this->assertEquals(1, $parentsWithADog[0]->getId());
    }

    protected function doSerializationGroupTests()
    {
//        //Limit which properties are hydrated using serialization groups
//        $this->objectRepository->clearSerializationGroups();
//        $this->objectRepository->addSerializationGroups(['Default']);
//        $this->objectRepository->setEntityClassName(TestParent::class);
//        $parent = $this->objectRepository->find(1);
//        $this->assertEquals(null, $parent->getChild());
//
//        $this->objectRepository->setEntityClassName(TestPolicy::class);
//        $expression = (new CriteriaExpression('contact.lastName', 'lastname_alias', '=', 'Skywalker'))
//            ->orWhere(
//                (new CriteriaExpression('status', null, '=', 'PAID'))
//                    ->andWhere(['effectiveStartDateTime', null, '>', new \DateTime('2018-12-15')])
//            )
//            ->orWhere(['id', null, '=', 19072010]);
//        $this->objectRepository->clearSerializationGroups();
//        $this->objectRepository->addSerializationGroups(['Default']);
//        $policies3a = $this->objectRepository->findBy([$expression], null, null, null, 'vehicle.id');
//        $this->assertSame(null, $policies3a[20]->contact->postcode);
    }

    protected function doAdvancedReadingTests()
    {
        //Load an object that contains two different instances of the same class
//        $this->objectRepository->clearSerializationGroups();
//        $this->objectRepository->addSerializationGroups(['Default', 'Full']);
        $this->objectRepository->setClassName(TestParent::class);
        $parent = $this->objectRepository->find(1);
        $this->assertEquals(TestCollection::class, get_class($parent->pets));

        $this->assertNotEquals($parent->getUser()->getId(), $parent->getChild()->getUser()->getId());
        $this->assertEquals('branch', $parent->getUser()->getType());
        $this->assertEquals('staff', $parent->getChild()->getUser()->getType());
        $this->assertEquals(4, count($parent->getPets()));
        $this->assertEquals('Flame', $parent->getPets()[0]->name);

        //Check that embedded value object was loaded
//        $this->assertEquals('London', $parent->getAddress()->getTown());

        //And that scalar join on embedded value object was also loaded
//        $this->assertEquals('United Kingdom', $parent->getAddress()->getCountryDescription());

        //Load using property of value object as criteria
//        $parentLoadedByAddress = $this->objectRepository->findOneBy(['address.town' => 'London']);
//        $this->assertEquals('London', $parentLoadedByAddress->getAddress()->getTown());

        //Load using scalar join value as criteria
//        $parentLoadByCountry = $this->objectRepository->findOneBy(['address.countryDescription' => 'United Kingdom']);
//        $this->assertEquals('GB', $parentLoadByCountry->getAddress()->getCountryCode());

        //Load using deeply nested property of value object as criteria
//        $nestedParentLoadedByAddress = $this->objectRepository->findOneBy(['child.parent.address.town' => 'London']);
//        $this->assertEquals('London', $nestedParentLoadedByAddress->getAddress()->getTown());
//        $nestedParentLoadedByCountry = $this->objectRepository->findOneBy(['child.parent.address.countryDescription' => 'Deepest Darkest Peru']);
//        $this->assertEquals('Eleanor Shellstrop', $nestedParentLoadedByCountry->getName());

        //Load using prefixed column of value object as criteria
//        $prefixedColumnCriteriaParent = $this->objectRepository->findOneBy(['child.address.town'=>'Loughborough']);
//        $this->assertEquals(2, $prefixedColumnCriteriaParent->getId());

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
        $this->assertEquals(2, count($employeeRob->unionMembers));
        $this->assertEquals('Tim', $employeeRob->mentee->mentee->mentee->name);

        //Check that multiple scalar joins on an embedded value object on a child object are hydrated
//        $this->objectRepository->setClassName(TestSecurityPass::class);
//        $securityPass = $this->objectRepository->findOneBy(['serialNo' => '1234567']);
//        $this->assertEquals('SB', $securityPass->employee->position->positionKey);
//        $this->assertEquals('Supreme Being', $securityPass->employee->position->positionValue);
//        $this->assertEquals('Lead PHP Developer and master of the black arts', $securityPass->employee->position->positionDescription);
//        $this->assertEquals('Tea boy', $securityPass->employee->mentee->position->positionDescription);
//        $this->assertEquals(2, $securityPass->employee->mentee->unionRep->id);
    }

    protected function doCustomQueryTests()
    {
        //Find with a custom repository
        $repositoryFactory = new RepositoryFactory($this->pdo);
        /** @var CustomRepository $customRepository */
        $customRepository = $repositoryFactory->createRepository(TestParent::class, CustomRepository::class);
        $parent = $customRepository->findParentUsingCustomSql(2);
        $this->assertEquals('Eleanor Shellstrop', $parent->getName());
        $this->assertEquals('Chidi', $parent->child->getName());
        $this->assertEquals(134, $parent->child->getHeight());
        $customRepository->clearCache();

        //Removed support for overrides for now...

        //Override query parts (check count and main query) - use strings and closures
//        $parents = $customRepository->findParentsUsingStringOverrides();
//        $this->assertEquals(3, count($parents));
//        $this->assertNull($parents[0]->getUser()); //User does not get loaded by custom SQL
//        $this->assertEquals('Eleanor Shellstrop', $parents[0]->getName());
//
//        $customRepository->clearCache();
//        $parents2 = $customRepository->findParentsUsingClosureOverrides();
//        $this->assertEquals(3, count($parents2));
//        $this->assertEquals('Eleanor Shellstrop', $parents2[0]->getName());
//        $this->assertEquals('alternative3@example.com', $parents2[0]->getUser()->getEmail());

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
