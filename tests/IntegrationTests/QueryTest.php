<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestPerson;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestUser;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;

class QueryTest extends IntegrationTestBase
{
    /**
     * Execute queries using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testQueryDefault()
    {
        $this->testName = 'Query default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testQueryMixed()
    {
        $this->testName = 'Query mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always lazy load everything possible
     */
    public function testQueryLazy()
    {
        $this->testName = 'Query lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overridding annotations to always eager load everything
     */
    public function testQueryEager()
    {
        $this->testName = 'Query eager' . $this->getCacheSuffix();
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

    protected function doTests()
    {
        $this->doSelectQueryTests();
        $this->doInsertQueryTests();
        $this->doUpdateQueryTests();
        $this->doReplaceQueryTests();
        $this->doDeleteQueryTests();
        $this->doExceptionTest();
    }

    /**
     * Any queries mentioned in the documentation are tested here to ensure we are not lying
     * @throws QueryException
     * @throws ObjectiphyException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function doSelectQueryTests()
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

        $query = QueryBuilder::create()
            ->select('id', 'name', 'email')
            ->from(TestUser::class)
            ->where('dateOfBirth', '>', '2000-01-01')
            ->orderBy(['name' => 'DESC'])
            ->buildSelectQuery();
        $users = $this->objectRepository->findBy($query);
        $this->assertEquals(2, count($users));
        $this->objectRepository->clearCache();

        $this->objectRepository->setClassName(TestUser::class);
        $query2 = QueryBuilder::create()
            ->where('dateOfBirth', '>', '2000-01-01')
            ->buildSelectQuery();
        $users2 = $this->objectRepository->findBy($query2);
        $this->assertEquals(array_sum(array_column($users, 'id')), array_sum(array_column($users2, 'id')));

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
        $contacts2 = $this->objectRepository->executeQuery($query2);
        $this->assertEquals(2, count($contacts2));
        $this->assertEquals('Skywalker', $contacts2[0]['lastName']);

        $query3 = QueryBuilder::create()
            ->select("CONCAT_WS(' ', %firstName%, %lastName%)")
            ->from(TestContact::class)
            ->where('lastName', '=', 'Skywalker')
            ->buildSelectQuery();
        $contacts3 = $this->objectRepository->findValuesBy($query3);
        $this->assertEquals(2, count($contacts3));
        $this->assertEquals('Luke Skywalker', $contacts3[0]);

        $query4 = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->buildSelectQuery();
        $contacts4 = $this->objectRepository->executeQuery($query4);
        $this->assertGreaterThan(40, count($contacts4));

        $this->objectRepository->setClassName(TestVehicle::class);
        $query5 = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->from(TestContact::class)
            ->buildSelectQuery();
        $contacts5 = $this->objectRepository->executeQuery($query5);
        $this->assertGreaterThan(40, $contacts5);

        $this->objectRepository->setConfigOption(ConfigOptions::BIND_TO_ENTITIES, true);
        $query6 = QueryBuilder::create()
            ->select('firstName', 'lastName', 'department.name')
            ->from(TestContact::class)
            ->limit(2)
            ->buildSelectQuery();
        $contacts6 = $this->objectRepository->executeQuery($query6);
        $this->assertEquals('Sales', $contacts6[0]->department->name);

        $this->objectRepository->setClassName(TestPerson::class);
        $query7 = QueryBuilder::create()
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
        $people = $this->objectRepository->executeQuery($query7);
        $this->assertEquals(3, count($people));

        $query8 = QueryBuilder::create()
            ->where('contact.lastName', QB::BEGINS_WITH, 'Mac')
            ->orStart()
            ->where('postcode', QB::EQUALS, 'PE3 8AF')
            ->and('email', QB::CONTAINS, 'info')
            ->orEnd()
            ->buildSelectQuery();
        $result8 = $this->objectRepository->executeQuery($query8);
        $this->assertEquals(2, count($result8));

        $query9 = QB::create()
            ->where('contact.lastName', QB::BEGINS_WITH, 'Mac')
            ->orStart()
            ->where('postcode', QB::EQUALS, 'PE3 8AF')
                ->and('email', QB::CONTAINS, 'info')
            ->orEnd()
            ->and('contact.loginId', QB::EQUALS, '%login.id%')
            ->buildSelectQuery();
        $result9 = $this->objectRepository->executeQuery($query9);
        $this->assertEquals(1, count($result9));

        $this->objectRepository->setClassName(TestPolicy::class);
        $postedValues = [
            'surname'   => 'smith', //Case insensitive
            'postcode'  => 'PE389QP',
            'email'     => 'peter@',
            'random'    => 'This should be ignored!'
        ];
        $query10 = QueryBuilder::create()
            ->where('contact.lastName', QB::BEGINS_WITH, ':surname')
            ->orStart()
                ->where('postcode', QB::EQUALS, ':postcode')
                ->and('email', QB::CONTAINS, ':email')
            ->orEnd()
            ->buildSelectQuery($postedValues);
        $result10 = $this->objectRepository->executeQuery($query10);
        $this->assertEquals(2, count($result10));

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
        $this->objectRepository->saveEntity($firstContact, null, $inserts, $updates);
        $this->assertEquals(0, $inserts);
        $this->assertEquals(1, $updates);
        $this->assertGreaterThan(0, $firstContact->id);

        $this->objectRepository->clearCache();
        $this->objectRepository->setClassName(TestContact::class);
        $refreshedContact = $this->objectRepository->find($firstContact->id);
        if ($this->getCacheSuffix()) {
            $this->assertEmpty($refreshedContact->title);
        } else {
            $this->assertNotEmpty($refreshedContact->title);
        }
    }

    protected function doInsertQueryTests()
    {
        $this->assertEquals(true, true);
    }

    protected function doUpdateQueryTests()
    {
        $this->setUp(); //To ensure we update the same number of records each time

        $query = QueryBuilder::create()
            ->update(TestContact::class)
            ->set([
                      'earnsCommission' => true,
                      'commissionRate' => 12.5
                  ])
            ->where('department.name', '=', 'Sales')
            ->buildUpdateQuery();
        $updateCount = $this->objectRepository->executeQuery($query);
        $this->assertEquals(7, $updateCount);

        $query2 = QueryBuilder::create()
            ->update(TestContact::class)
            ->set([
                      'higherRateEarner' => '%commissionRate% > 14'
                  ])
            ->buildUpdateQuery();
        $updateCount = $this->objectRepository->executeQuery($query2);
        $this->assertEquals(2, $updateCount);
    }

    protected function doReplaceQueryTests()
    {
        $this->assertEquals(true, true);
    }

    protected function doDeleteQueryTests()
    {
        $query = QueryBuilder::create()
            ->delete(TestContact::class)
            ->where('isPermanent', '=', false)
            ->buildDeleteQuery();
        $insertCount = 0;
        $updateCount = 0;
        $deleteCount = 0;
        $affectedCount = $this->objectRepository->executeQuery($query, $insertCount, $updateCount, $deleteCount);
        $this->assertEquals(5, $affectedCount);
        $this->assertEquals(0, $insertCount);
        $this->assertEquals(0, $updateCount);
        $this->assertEquals(5, $deleteCount);
        $sql = $this->objectRepository->getSql();
        $expected = "DELETE FROM \n`objectiphy_test`.`contact`\nWHERE 1\n    AND `objectiphy_test`.`contact`.`is_permanent` = '0'";
        $this->assertEquals($expected, $sql);
    }

    protected function doExceptionTest()
    {
        $this->setUp(); //Restore anything that was deleted by earlier tests
        $criteria = ['departments' => ['Sales', 'Finance']];
        $query = QueryBuilder::create()
            ->select('firstName', 'lastName')
            ->from(TestContact::class)
            ->innerJoin(TestVehicle::class, 'v')
            ->on('id', '=', 'v.ownerContactId')
            ->and('v.type', '=', 'car')
            ->where('department.name', 'IN', ':departments')
            ->and('isPermanent', '=', true)
            ->buildSelectQuery($criteria);
        $contacts = $this->objectRepository->findBy($query);
        $this->assertEquals(2, count($contacts));

        //As we did not retrieve the primary key, attempts to save should fail unless cache is disabled
        $firstContact = reset($contacts);
        $firstContact->lastName = 'NewSurname';
        if (!$this->getCacheSuffix()) {
            $this->expectException(QueryException::class);
            $this->objectRepository->saveEntity($firstContact);
        } else {
            $inserts = 0;
            $updates = 0;
            $this->objectRepository->saveEntity($firstContact, null, $inserts, $updates);
            $this->assertEquals(1, $inserts);
            $this->assertEquals(0, $updates);
            $this->assertGreaterThan(0, $firstContact->id);
        }
    }
}
