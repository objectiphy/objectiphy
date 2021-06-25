<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use App\Entity\Policy;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestPerson;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestUser;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicleGroupRate;

class SelectQueryTest extends IntegrationTestBase
{
    /**
     * Execute queries using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testQueryDefault()
    {
        $this->testName = 'Select query default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testQueryMixed()
    {
        $this->testName = 'Select query mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always lazy load everything possible
     */
    public function testQueryLazy()
    {
        $this->testName = 'Select query lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overridding annotations to always eager load everything
     */
    public function testQueryEager()
    {
        $this->testName = 'Select query eager' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    //Repeat with the cache turned off
    public function testQueryDefaultNoCache()
    {
        $this->disableCache();
        $this->testQueryDefault();
    }

    public function testQueryMixedNoCache()
    {
        $this->disableCache();
        $this->testQueryMixed();
    }

    public function testQueryLazyNoCache()
    {
        $this->disableCache();
        $this->testQueryLazy();
    }

    public function testQueryEagerNoCache()
    {
        $this->disableCache();
        $this->testQueryEager();
    }

    /**
     * Separate tests for each page in the documentation that shows a query example
     * @throws ObjectiphyException
     * @throws QueryException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function doTests()
    {
        $this->doIntroTests();
        $this->doQueryBuilderTests();
        $this->doRunningQueryTests();
        $this->doSelectQueryTests();
        $this->doCriteriaTests();
        $this->doJoinTests();
    }

    /**
     * Any queries mentioned in the documentation are tested here to ensure we are not lying
     * @throws QueryException
     * @throws ObjectiphyException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function doIntroTests()
    {
        $this->objectRepository->setClassName(TestContact::class);
        $criteria = ['department.name' => 'Sales'];
        $contacts = $this->objectRepository->findBy($criteria);
        $this->assertEquals(9, count($contacts));

        $this->objectRepository->setClassName(TestPolicy::class);
        $this->objectRepository->setConfigOption(ConfigOptions::BIND_TO_ENTITIES, false);
        $policiesArray = $this->objectRepository->findBy([
            'policyNo'=>[
            'operator'=>'LIKE',
            'value'=>'P1235%'
            ]
        ]);
        $this->assertEquals(6, count($policiesArray));

        $this->objectRepository->setConfigOption(ConfigOptions::BIND_TO_ENTITIES, true);
        $criteria = ['departments' => ['Sales', 'Finance']];
        $query = QueryBuilder::create()
            ->select('id', 'firstName', 'lastName')
            ->from(TestContact::class)
            ->innerJoin(TestVehicle::class, 'v')
            ->on('id', '=', 'v.ownerContactId')
            ->and('v.type', '=', 'car')
            ->where('department.name', 'IN', ':departments')
            ->and('isPermanent', '=', true)
            ->buildSelectQuery($criteria);
        $contacts = $this->objectRepository->findBy($query);
        $this->assertEquals(2, count($contacts));
        $this->assertEquals(123, $contacts[0]->id);
        $this->assertEquals(124, $contacts[1]->id);

        //As we retrieved the primary key, save should succeed but only update the changed properties unless cache is disabled.
        $firstContact = reset($contacts);
        $firstContact->lastName = 'NewSurname';
        $inserts = 0;
        $updates = 0;
        $this->objectRepository->saveEntity($firstContact, null, false, $inserts, $updates);
        $this->assertEquals(0, $inserts);
        $this->assertEquals(1, $updates);
        $this->assertGreaterThan(0, $firstContact->id);

        //$this->objectRepository->clearCache();
        $this->objectRepository->setClassName(TestContact::class);
        $refreshedContact = $this->objectRepository->find($firstContact->id);
        if ($this->getCacheSuffix()) {
            $this->assertEmpty($refreshedContact->title);
        } else {
            $this->assertNotEmpty($refreshedContact->title);
        }
    }

    protected function doQueryBuilderTests()
    {
        $query = QueryBuilder::create()
            ->select('id', 'type', 'email')
            ->from(TestUser::class)
            ->where('dateOfBirth', '>', '2000-01-01')
            ->orderBy(['email' => 'DESC'])
            ->buildSelectQuery();
        $users = $this->objectRepository->findBy($query);
        $this->assertEquals(2, count($users));
        //$this->objectRepository->clearCache();

        $this->objectRepository->setClassName(TestUser::class);
        $query2 = QueryBuilder::create()
            ->where('dateOfBirth', '>', '2000-01-01')
            ->buildSelectQuery();
        $users2 = $this->objectRepository->findBy($query2);
        $this->assertEquals(array_sum(array_column($users, 'id')), array_sum(array_column($users2, 'id')));
    }

    protected function doRunningQueryTests()
    {
        $this->setUp(); //Forget about anything added by previous tests
        
        $query = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->from(TestContact::class)
            ->where('lastName', '=', 'Skywalker')
            ->buildSelectQuery();
        $contacts = $this->objectRepository->executeQuery($query);
        $this->assertEquals(2, count($contacts));

        $this->objectRepository->setConfigOption(ConfigOptions::BIND_TO_ENTITIES, false);
        $query2 = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->from(TestContact::class)
            ->where('lastName', '=', 'Skywalker')
            ->buildSelectQuery();
        $contacts2 = $this->objectRepository->findBy($query2);
        $this->assertEquals(2, count($contacts2));
        $this->assertEquals('Skywalker', $contacts2[0]['lastName']);
    }

    protected function doSelectQueryTests()
    {
        $query = QueryBuilder::create()
            ->select("CONCAT_WS(' ', %firstName%, %lastName%)")
            ->from(TestContact::class)
            ->where('lastName', '=', 'Skywalker')
            ->buildSelectQuery();
        $contacts = $this->objectRepository->findValuesBy($query);
        $this->assertEquals(2, count($contacts));
        $this->assertEquals('Luke Skywalker', $contacts[0]);

        $query2 = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->buildSelectQuery();
        $contacts2 = $this->objectRepository->executeQuery($query2);
        $this->assertGreaterThan(40, count($contacts2));

        $this->objectRepository->setClassName(TestVehicle::class);
        $query3 = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->from(TestContact::class)
            ->buildSelectQuery();
        $contacts3 = $this->objectRepository->executeQuery($query3);
        $this->assertGreaterThan(40, $contacts3);

        $this->objectRepository->setConfigOption(ConfigOptions::BIND_TO_ENTITIES, true);
        $query4 = QueryBuilder::create()
            ->select('firstName', 'lastName', 'department.name')
            ->from(TestContact::class)
            ->limit(2)
            ->buildSelectQuery();
        $contacts4 = $this->objectRepository->executeQuery($query4);
        $this->assertEquals('Sales', $contacts4[0]->department->name);

        $this->objectRepository->setConfigOption(ConfigOptions::BIND_TO_ENTITIES, false);
        $query5 = QB::create()->select('COUNT(*)')->from(Policy::class)->buildSelectQuery();
        $count = $this->objectRepository->findOneValueBy($query5);
        $this->assertEquals(44, $count);
    }

    protected function doCriteriaTests()
    {
        $this->objectRepository->setClassName(TestPerson::class);
        $query = QueryBuilder::create()
            ->orStart()
                ->where('firstName', '=', 'Marty')
                ->and('lastName', '=', 'McFly')
            ->orEnd()
            ->orStart()
                ->where('firstName', '=', 'Emmet')
                ->and('lastName', '=', 'Brown')
            ->orEnd()
            ->or('car', '=', 'DeLorean')
            ->andStart()
                ->where('year', '=', 1985)
                ->or('year', '=', 1955)
            ->andEnd()
            ->buildSelectQuery();
        $people = $this->objectRepository->executeQuery($query);
        $this->assertEquals(3, count($people));

        $query2 = QueryBuilder::create()
            ->where('contact.lastName', QB::BEGINS_WITH, 'Mac')
            ->orStart()
                ->where('postcode', QB::EQUALS, 'PE3 8AF')
                ->and('email', QB::CONTAINS, 'info')
            ->orEnd()
            ->buildSelectQuery();
        $result2 = $this->objectRepository->executeQuery($query2);
        $this->assertEquals(2, count($result2));

        $query3 = QB::create()
            ->where('contact.lastName', QB::BEGINS_WITH, 'Mac')
            ->orStart()
                ->where('postcode', QB::EQUALS, 'PE3 8AF')
                ->and('email', QB::CONTAINS, 'info')
            ->orEnd()
            ->and('contact.loginId', QB::EQUALS, '%login.id%')
            ->buildSelectQuery();
        $result3 = $this->objectRepository->executeQuery($query3);
        $this->assertEquals(1, count($result3));

        $this->objectRepository->setClassName(TestPolicy::class);
        $postedValues = [
            'surname' => 'smith', //Case insensitive
            'postcode' => 'PE389QP',
            'email' => 'peter@',
            'random' => 'This should be ignored!'
        ];
        $query4 = QueryBuilder::create()
            ->where('contact.lastName', QB::BEGINS_WITH, ':surname')
            ->orStart()
            ->where('postcode', QB::EQUALS, ':postcode')
            ->and('email', QB::CONTAINS, ':email')
            ->orEnd()
            ->buildSelectQuery($postedValues);
        $result4 = $this->objectRepository->executeQuery($query4);
        $this->assertEquals(2, count($result4));

        //Order by child property
        $query5 = QueryBuilder::create()
            ->orderBy(['contact.lastName' => 'DESC'])
            ->limit(5)
            ->buildSelectQuery();
        $result5 = $this->objectRepository->executeQuery($query5);
        $this->assertEquals(5, count($result5));
        $this->assertEquals('Walker', $result5[0]->contact->lastName);
        $this->assertEquals('Urquhart', $result5[1]->contact->lastName);

        $this->objectRepository->setClassName(TestChild::class);
        $query6 = QueryBuilder::create()
            ->where('parent.pets.type', QB::EQUALS, 'dog')
            ->buildSelectQuery();
        $result6 = $this->objectRepository->executeQuery($query6);
        $this->assertEquals(1, count($result6));
    }

    protected function doJoinTests()
    {
        $this->objectRepository->setClassName(TestPolicy::class);
        $query = QueryBuilder::create()
            ->leftJoin(TestPolicy::class, 'p2')
            ->on('p2.policyNo', '=', 'policyNo')
            ->and('p2.id', '>', 'id')
            ->and('p2.status', '=', 'INFORCE')
            ->and('p2.modification', '=', 'CANCELLED')
            ->where('modification', QB::NOT_EQ, 'CANCELLED')
            ->and('p2.id', 'IS', null)
            ->buildSelectQuery();
        $result = $this->objectRepository->executeQuery($query);
        $this->assertEquals(1, count($result));

        //Join to a class whose name starts with the parent class - ensure replacement does not get confused
        $query = QB::create()
            ->select('group50', 'r.rate')
            ->from(TestVehicle::class)
            ->leftJoin(TestVehicleGroupRate::class,'r')
                ->on('group50',QB::EQ,'r.group50')
            ->where('abiCode', QB::EQ, '12345678')
            ->andStart()
                ->where('r.businessType',QB::EQ, 'NEW')
                ->or('r.businessType', QB::EQ, 'ALL')
            ->andEnd()
            ->buildSelectQuery();
        $values = $this->objectRepository->findValuesBy($query);
        $this->assertEquals(26, count($values));

        //Use grouped criteria in a join condition
        $query = QB::create()
            ->select('group50', 'r.rate')
            ->from(TestVehicle::class)
            ->leftJoin(TestVehicleGroupRate::class, 'r')
                ->on('r.group50', QB::EQ, 'group50')
                ->andStart()
                    ->where('r.businessType', QB::EQ, 'NEW')
                    ->or('r.businessType', QB::EQ, 'ALL')
                ->andEnd()
                ->and('r.ratingScheme', QB::EQ, 1)
            ->where('abiCode', QB::EQ, 12345678)
            ->buildSelectQuery();
        $values2 = $this->objectRepository->findValuesBy($query, '', null, 'group50', false);
        $this->assertEquals(2, count($values2));

        //Index values
        $query = QB::create()
            ->select('group50', 'r.rate')
            ->from(TestVehicle::class)
            ->leftJoin(TestVehicleGroupRate::class, 'r')
                ->on('r.group50', QB::EQ, 'group50')
            ->where('abiCode', QB::EQ, 12345678)
                ->andStart()
                    ->where('r.businessType', QB::EQ, 'NEW')
                    ->or('r.businessType', QB::EQ, 'ALL')
                    ->or('r.businessType', QB::IS, null)
                ->andEnd()
                ->andStart()
                    ->where('r.ratingScheme', QB::EQ, 1)
                    ->or('r.ratingScheme', QB::IS, null)
                ->andEnd();
        $values3 = $this->objectRepository->findValuesBy($query->buildSelectQuery(), 'r.rate', null, 'group50');
        $this->assertEquals(2, count($values3));
        $this->assertEquals(1, array_key_first($values3));
    }
}
