<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;

class DeleteQueryTest extends IntegrationTestBase
{
    /**
     * Execute queries using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testQueryDefault()
    {
        $this->testName = 'Delete query default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testQueryMixed()
    {
        $this->testName = 'Delete query mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always lazy load everything possible
     */
    public function testQueryLazy()
    {
        $this->testName = 'Delete query lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overridding annotations to always eager load everything
     */
    public function testQueryEager()
    {
        $this->testName = 'Delete query eager' . $this->getCacheSuffix();
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
        $this->doDeleteQueryTests();
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
}
