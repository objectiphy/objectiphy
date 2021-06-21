<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;

class UpdateQueryTest extends IntegrationTestBase
{
    /**
     * Execute queries using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testQueryDefault()
    {
        $this->testName = 'Update query default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testQueryMixed()
    {
        $this->testName = 'Update query mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always lazy load everything possible
     */
    public function testQueryLazy()
    {
        $this->testName = 'Update query lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overridding annotations to always eager load everything
     */
    public function testQueryEager()
    {
        $this->testName = 'Update query eager' . $this->getCacheSuffix();
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
        $this->doUpdateQueryTests();
    }

    protected function doUpdateQueryTests()
    {
        $this->setUp(); //Forget about anything added by previous tests
        
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
}
