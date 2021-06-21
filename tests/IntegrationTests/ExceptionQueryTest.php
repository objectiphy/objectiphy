<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;

class ExceptionQueryTest extends IntegrationTestBase
{
    /**
     * Execute queries using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testQueryDefault()
    {
        $this->testName = 'Exception query default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testQueryMixed()
    {
        $this->testName = 'Exception query mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always lazy load everything possible
     */
    public function testQueryLazy()
    {
        $this->testName = 'Exception query lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overridding annotations to always eager load everything
     */
    public function testQueryEager()
    {
        $this->testName = 'Exception query eager' . $this->getCacheSuffix();
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
     */
    protected function doTests()
    {
        $this->doExceptionQueryTest();
    }

    protected function doExceptionQueryTest()
    {
        $this->setUp(); //Forget about anything added by previous tests
        
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
            $this->objectRepository->saveEntity($firstContact, null, false, $inserts, $updates);
            $this->assertEquals(1, $inserts);
            $this->assertEquals(0, $updates);
            $this->assertGreaterThan(0, $firstContact->id);
        }
    }
}
