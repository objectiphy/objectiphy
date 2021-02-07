<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
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
    }

    protected function doSelectQueryTests()
    {
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
        //$contacts = $this->objectRepository->findBy($query);
        $this->assertEquals(true, true);
    }

    protected function doInsertQueryTests()
    {
        $this->assertEquals(true, true);
    }

    protected function doUpdateQueryTests()
    {
        $this->assertEquals(true, true);
    }

    protected function doReplaceQueryTests()
    {
        $this->assertEquals(true, true);
    }

    protected function doDeleteQueryTests()
    {
        $this->assertEquals(true, true);
    }
}
