<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestStudent;

class InsertQueryTest extends IntegrationTestBase
{
    /**
     * Execute queries using annotations to determine eager/lazy loading - doctrine annotations use doctrine defaults
     * (which is to lazy load everything unless otherwise stated), objectiphy annotations use objectiphy defaults
     * (which is to eager load -to-one relationships, and lazy load -to-many relationships).
     */
    public function testQueryDefault()
    {
        $this->testName = 'Insert query default' . $this->getCacheSuffix();
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always eager load -to-one and lazy load -to-many relationships
     */
    public function testQueryMixed()
    {
        $this->testName = 'Insert query mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overriding annotations to always lazy load everything possible
     */
    public function testQueryLazy()
    {
        $this->testName = 'Insert query lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    /**
     * Execute queries, overridding annotations to always eager load everything
     */
    public function testQueryEager()
    {
        $this->testName = 'Insert query eager' . $this->getCacheSuffix();
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
        $this->doInsertQueryTests();
        $this->doMultipleInsertTests();
        $this->doIterateInsertTests();
    }

    protected function doInsertQueryTests()
    {
        $countQuery = QB::create()
            ->select('COUNT(*)')
            ->from(TestContact::class)
            ->where('lastName', '=', 'Skywalker')
            ->limit(1) //Necessary to ensure a single value is returned
            ->buildSelectQuery();
        $count = intval($this->objectRepository->executeQuery($countQuery));
        $this->assertEquals(2, $count);

        $insertQuery = QB::create()
            ->insert(TestContact::class)
            ->set(['firstName' => 'Englebert', 'lastName' => 'Skywalker'])
            ->buildInsertQuery();
        $result = $this->objectRepository->executeQuery($insertQuery);
        $this->assertEquals(1, $result);
        $lastInsertId = $this->objectRepository->getLastInsertId();
        $this->assertGreaterThan(1, $lastInsertId);

        $count2 = intval($this->objectRepository->executeQuery($countQuery));
        $this->assertEquals(3, $count2);

        $this->objectRepository->setClassName(TestStudent::class);
        $insertQuery2 = QB::create()
            ->set(['firstName' => 'Slartibartfast', 'lastName' => 'Douglas', 'iq' => 200])
            ->buildInsertQuery();
        $result2 = $this->objectRepository->executeQuery($insertQuery2);
        $this->assertEquals(1, $result2);
    }

    protected function doMultipleInsertTests()
    {
        $countQuery = QB::create()
            ->select('COUNT(*)')
            ->from(TestContact::class)
            ->where('lastName', '=', 'Skywalker')
            ->limit(1) //Necessary to ensure a single value is returned
            ->buildSelectQuery();
        $count = intval($this->objectRepository->executeQuery($countQuery));
        $this->assertEquals(3, $count);

        $insertQuery = QB::create()
            ->insert(TestContact::class)
            ->set(['firstName' => 'Englebert', 'lastName' => 'Skywalker'])
            ->set(['firstName' => 'Jemima', 'lastName' => 'Skywalker'])
            ->set(['firstName' => 'Jedediah', 'lastName' => 'Skywalker'])
            ->set(['firstName' => 'Trixie', 'lastName' => 'Skywalker'])
            ->buildInsertQuery();
        $result = $this->objectRepository->executeQuery($insertQuery);
        $this->assertEquals(4, $result);
        $lastInsertId = $this->objectRepository->getLastInsertId();
        $this->assertGreaterThan(1, $lastInsertId);

        $count2 = intval($this->objectRepository->executeQuery($countQuery));
        $this->assertEquals(7, $count2);
    }

    protected function doIterateInsertTests()
    {
        //Test using clearAllPrimaryKeyValues to insert a copy of an existing record
        $this->objectRepository->setClassName(TestPolicy::class);
        $policy = $this->objectRepository->findOneBy(['id' => 19071974]);
        $this->objectRepository->clearAllPrimaryKeyValues($policy, ['underwriter', 'contact.department']);
        $this->assertNull($policy->id); return;
        $this->assertNotNull($policy->underwriter->id);
        $this->assertNull($policy->contact->id);
        $this->assertNull($policy->contact->securityPass->id);
        $this->assertNull($policy->contact->securityPass->employee->id);
        $this->assertNotNull($policy->contact->nonPkChild->nebulousIdentifier);
        $this->assertNotNull($policy->contact->department->id);
        $this->assertSame($policy, $policy->vehicle->policy);
        $this->objectRepository->saveEntity($policy);
        $this->assertNotEquals(19071974, $policy->id);
        $origPolicy = $this->objectRepository->findOneBy(['id' => 19071974]);
        $this->assertEquals(19071974, $origPolicy->id);
        $this->assertEquals($origPolicy->underwriter->id, $policy->underwriter->id);
        $this->assertEquals($origPolicy->contact->department->id, $policy->contact->department->id);
        $this->assertNotEquals($origPolicy->contact->id, $policy->contact->id);
        $this->assertNotEquals($origPolicy->vehicle->id, $policy->vehicle->id);
        $this->assertNotEquals($origPolicy->contact->securityPass->id, $policy->contact->securityPass->id);
    }
}
