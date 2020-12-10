<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\UnitTests;

use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\QueryBuilder;
use Objectiphy\Objectiphy\Query\CB;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    /** @var QueryBuilder */
    protected $object;

    protected function setup(): void
    {
        $this->object = QueryBuilder::create();
    }

    public function testWhere()
    {
        //As this is just an alias of andWhere, ensure we get the same results with both
        $this->testAndWhere('where');
    }

    public function testAndWhere($method = 'andWhere')
    {
        $this->object->{$method}('propertyName', '>', 1);
        $array = $this->object->toArray();
        $this->assertArrayHasKey('propertyName', $array);
        $this->assertEquals('>', $array['propertyName']['operator']);
        $this->assertEquals(1, $array['propertyName']['value']);

        $this->object->{$method}('propertyTwo', QB::BETWEEN, ['A', ':B']);
        $array = $this->object->toArray();
        $expression = $array['propertyTwo'];
        $this->assertEquals('propertyTwo', $expression['propertyName']);
        $this->assertEquals('BETWEEN', $expression['operator']);
        $this->assertEquals('A', $expression['value']);
        $this->assertEquals('B', $expression['alias2']);

        $nestedQueryBuilder = QB::create();
        $nestedQueryBuilder->{$method}('propertyThree', QB::NOT_EQUALS, ':propertyThree');
        $this->object->{$method}('propertyTwo', 'NOT IN', [1, 'A', ':B'], $nestedQueryBuilder);
        $array = $this->object->toArray();
        $expression = $array['propertyTwo']['andExpressions'][0];
        $this->assertEquals('propertyTwo', $expression['propertyName']);
        $this->assertEquals(QB::NOT_IN, $expression['operator']);
        $this->assertEquals([1, 'A', ':B'], $expression['value']);
        $this->assertEquals('propertyThree', $expression['andExpressions'][0]['propertyName']);

        $this->object->{$method}('propertyFour', QB::IS, null, null, 'SUM', 'loginId');
        $array = $this->object->toArray();
        $expression = $array['propertyFour'];
        $this->assertEquals('propertyFour', $expression['propertyName']);
        $this->assertEquals('IS', $expression['operator']);
        $this->assertNull($expression['value']);
        $this->assertEquals('SUM', $expression['aggregateFunction']);
        $this->assertEquals('loginId', $expression['aggregateGroupByProperty']);
    }

    public function testOrWhere()
    {
        $this->object->orWhere('propertyName', QB::LTE, 10);
        $array = $this->object->toArray();
        $this->assertArrayHasKey('propertyName', $array);
        $this->assertEquals('<=', $array['propertyName']['operator']);
        $this->assertEquals(10, $array['propertyName']['value']);

        $this->object->orWhere('propertyTwo', QB::BETWEEN, [5, 50]);
        $array = $this->object->toArray();
        $expression = $array['propertyName']['orExpressions'][0];
        $this->assertEquals('propertyTwo', $expression['propertyName']);
        $this->assertEquals('BETWEEN', $expression['operator']);
        $this->assertEquals(5, $expression['value']);
        $this->assertEquals(50, $expression['value2']);

        $nestedQueryBuilder = QB::create();
        $nestedQueryBuilder->orWhere('propertyThree', 'CONTAINS', ':snippet');
        $this->object->orWhere('propertyTwo', QB::IN, [1, 'A', ':B'], $nestedQueryBuilder);
        $array = $this->object->toArray();
        $expression = $array['propertyName']['orExpressions'][1];
        $this->assertEquals('propertyTwo', $expression['propertyName']);
        $this->assertEquals('IN', $expression['operator']);
        $this->assertEquals([1, 'A', ':B'], $expression['value']);
        $this->assertEquals('propertyThree', $expression['orExpressions'][0]['propertyName']);

        $this->object->orWhere('propertyFour', QB::LIKE, 'Some%thing%', null, 'AVG', 'policyNumber');
        $array = $this->object->toArray();
        $expression = $array['propertyName']['orExpressions'][2];
        $this->assertEquals('propertyFour', $expression['propertyName']);
        $this->assertEquals('LIKE', $expression['operator']);
        $this->assertEquals('Some%thing%', $expression['value']);
        $this->assertEquals('AVG', $expression['aggregateFunction']);
        $this->assertEquals('policyNumber', $expression['aggregateGroupByProperty']);
    }

    public function testBuildUnbound()
    {
        $this->testAndWhere();
        $beforeBuild = $this->object->toArray();
        $this->object->build([], false);
        $array = $this->object->toArray();
        //As we have not removed unbound items, arrays should be identical
        $this->assertEquals($beforeBuild, $array);
        $this->assertEquals(3, count($array));

        //Now we will remove unbound, so there will be a difference...
        $this->object->build();
        $array = $this->object->toArray();
        $this->assertNotEquals($beforeBuild, $array);
        $this->assertEquals(2, count($array));
    }

    public function testBuildMixedNoRemove()
    {
        $this->testAndWhere();
        $this->object->build(['B' => 'The Letter B'], false);
        $array = $this->object->toArray();
        $this->assertEquals('The Letter B', $array['propertyTwo']['value2']);
        $this->assertEquals('The Letter B', $array['propertyTwo']['andExpressions'][0]['value'][2]);
        $this->assertEquals(1, count($array['propertyTwo']['andExpressions'][0]['andExpressions']));
        $this->assertEquals('propertyThree', $array['propertyTwo']['andExpressions'][0]['andExpressions'][0]['alias']);
    }

    public function testBuildMixedRemove()
    {
        $this->testAndWhere();
        $this->object->build(['B' => 'The Letter B']);
        $array = $this->object->toArray();
        $this->assertEquals('The Letter B', $array['propertyTwo']['value2']);
        $this->assertEquals('The Letter B', $array['propertyTwo']['andExpressions'][0]['value'][2]);
        $this->assertEquals(0, count($array['propertyTwo']['andExpressions'][0]['andExpressions']));
    }

    public function testBuildBound()
    {
        $this->testAndWhere();
        $this->object->build(['B'=>'The Letter B', 'propertyThree'=>3], false);
        $array = $this->object->toArray();
        $this->assertEquals('The Letter B', $array['propertyTwo']['value2']);
        $this->assertEquals('The Letter B', $array['propertyTwo']['andExpressions'][0]['value'][2]);
        $this->assertEquals(1, count($array['propertyTwo']['andExpressions'][0]['andExpressions']));
        $this->assertEquals(3, $array['propertyTwo']['andExpressions'][0]['andExpressions'][0]['value']);
    }
    
    public function testBuildOrderBy()
    {
        $orderBy = $this->object->buildOrderBy(['lastName'=>'DESC', 'id'=>'ASC'], ['lastName'=>'customer.surname', 'id'=>'policy.id']);
        $this->assertEquals(['customer.surname'=>'DESC', 'policy.id'=>'ASC'], $orderBy);

        $orderBy2 = $this->object->buildOrderBy(['lastName'=>'DESC', 'id'=>'ASC', 'somethingElse'=>'ASC'], ['lastName'=>'customer.surname', 'id'=>'policy.id']);
        $this->assertEquals(['customer.surname'=>'DESC', 'policy.id'=>'ASC', 'somethingElse'=>'ASC'], $orderBy2);

        $orderBy3 = $this->object->buildOrderBy(['lastName'=>'DESC', 'id'=>'DESC', 'somethingElse'=>'ASC'], ['lastName'=>'customer.surname', 'id'=>'policy.id'], false);
        $this->assertEquals(['customer.surname'=>'DESC', 'policy.id'=>'DESC'], $orderBy3);
    }

    public function testReset()
    {
        $this->object->where('propertyName', '>', 1);
        $array = $this->object->toArray();
        $this->assertNotEmpty($array);

        $this->object->reset();
        $array = $this->object->toArray();
        $this->assertEmpty($array);
    }

    public function testNormalizeIdList()
    {
        $criteria = [1, 2, 5, 10, 349282];
        $normalized = $this->object->normalize($criteria, 'policyId');
        $this->assertEquals(1, count($normalized));
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[0]);
        $this->assertEquals('policyId', $normalized[0]->propertyName);
        $this->assertEquals(QB::IN, $normalized[0]->operator);
        $this->assertEquals($criteria, $normalized[0]->value);
    }

    public function testNormalizeSimpleArray()
    {
        $criteria = ['policyId' => 123, 'lastName'=>'Smith'];
        $normalized = $this->object->normalize($criteria);
        $this->assertEquals(2, count($normalized));
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[0]);
        $this->assertEquals('policyId', $normalized[0]->propertyName);
        $this->assertEquals('=', $normalized[0]->operator);
        $this->assertEquals(123, $normalized[0]->value);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[1]);
        $this->assertEquals('lastName', $normalized[1]->propertyName);
        $this->assertEquals('=', $normalized[1]->operator);
        $this->assertEquals('Smith', $normalized[1]->value);

        return $criteria;
    }

    public function testNormalizeComplexArray()
    {
        $criteria = [
            'policyId' => ['operator'=>'!=', 'value'=>100],
            'startDate'=>['operator'=>'BETWEEN', 'value'=>'2019-01-01', 'value2'=>'2019-12-31'],
            'somethingElse'=>['value'=>321, 'or'=>['nestedOr'=>['operator'=>'IN', 'value'=>[1, 'A']]]]
        ];
        $normalized = $this->object->normalize($criteria);
        $this->assertEquals(3, count($normalized));
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[0]);
        $this->assertEquals('policyId', $normalized[0]->propertyName);
        $this->assertEquals(QB::NOT_EQUALS, $normalized[0]->operator);
        $this->assertEquals(100, $normalized[0]->value);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[1]);
        $this->assertEquals('startDate', $normalized[1]->propertyName);
        $this->assertEquals(QB::BETWEEN, $normalized[1]->operator);
        $this->assertEquals('2019-01-01', $normalized[1]->value);
        $this->assertEquals('2019-12-31', $normalized[1]->value2);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[2]);
        $this->assertEquals('somethingElse', $normalized[2]->propertyName);
        $this->assertEquals(QB::EQ, $normalized[2]->operator);
        $this->assertEquals(321, $normalized[2]->value);
        $this->assertEquals(1, count($normalized[2]->orExpressions));

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[2]->orExpressions[0]);
        $this->assertEquals('nestedOr', $normalized[2]->orExpressions[0]->propertyName);
        $this->assertEquals(QB::IN, $normalized[2]->orExpressions[0]->operator);
        $this->assertEquals([1, 'A'], $normalized[2]->orExpressions[0]->value);

        return $criteria;
    }

    public function testNormalizeExpression()
    {
        $expression = new CriteriaExpression('policyId', null, '>', 100);
        $expression2 = new CriteriaExpression('lastName', null, 'CONTAINS', 'Mc', null, null, [], [
            new CriteriaExpression('lastName', null,'BEGINSWITH', 'Mac')
        ]);
        $criteria = [$expression, $expression2];
        $normalized = $this->object->normalize($criteria);

        $this->assertEquals(2, count($normalized));
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[0]);
        $this->assertEquals('policyId', $normalized[0]->propertyName);
        $this->assertEquals(QB::GREATER_THAN, $normalized[0]->operator);
        $this->assertEquals(100, $normalized[0]->value);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[1]);
        $this->assertEquals('lastName', $normalized[1]->propertyName);
        $this->assertEquals(QB::CONTAINS, $normalized[1]->operator);
        $this->assertEquals('Mc', $normalized[1]->value);
        $this->assertEquals(1, count($normalized[1]->orExpressions));

        $this->assertEquals('lastName', $normalized[1]->orExpressions[0]->propertyName);
        $this->assertEquals(QB::BEGINS_WITH, $normalized[1]->orExpressions[0]->operator);
        $this->assertEquals('Mac', $normalized[1]->orExpressions[0]->value);

        return $criteria;
    }

    public function testNormalizeMixed()
    {
        $criteria = array_merge(
            $this->testNormalizeSimpleArray(),
            $this->testNormalizeComplexArray(),
            $this->testNormalizeExpression()
        );
        $normalized = $this->object->normalize($criteria);

        //Simple array
        $this->assertEquals(6, count($normalized));
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[0]);
        $this->assertEquals('policyId', $normalized[0]->propertyName);
        $this->assertEquals(QB::NOT_EQ, $normalized[0]->operator);
        $this->assertEquals(100, $normalized[0]->value);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[1]);
        $this->assertEquals('lastName', $normalized[1]->propertyName);
        $this->assertEquals('=', $normalized[1]->operator);
        $this->assertEquals('Smith', $normalized[1]->value);

        //Complex array
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[2]);
        $this->assertEquals('startDate', $normalized[2]->propertyName);
        $this->assertEquals(QB::BETWEEN, $normalized[2]->operator);
        $this->assertEquals('2019-01-01', $normalized[2]->value);
        $this->assertEquals('2019-12-31', $normalized[2]->value2);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[3]);
        $this->assertEquals('somethingElse', $normalized[3]->propertyName);
        $this->assertEquals(QB::EQUALS, $normalized[3]->operator);
        $this->assertEquals(321, $normalized[3]->value);
        $this->assertEquals(1, count($normalized[3]->orExpressions));

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[3]->orExpressions[0]);
        $this->assertEquals('nestedOr', $normalized[3]->orExpressions[0]->propertyName);
        $this->assertEquals(QB::IN, $normalized[3]->orExpressions[0]->operator);
        $this->assertEquals([1, 'A'], $normalized[3]->orExpressions[0]->value);

        //Expressions
        $this->assertInstanceOf(CriteriaExpression::class, $normalized[4]);
        $this->assertEquals('policyId', $normalized[4]->propertyName);
        $this->assertEquals(QB::GT, $normalized[4]->operator);
        $this->assertEquals(100, $normalized[4]->value);

        $this->assertInstanceOf(CriteriaExpression::class, $normalized[5]);
        $this->assertEquals('lastName', $normalized[5]->propertyName);
        $this->assertEquals(QB::CONTAINS, $normalized[5]->operator);
        $this->assertEquals('Mc', $normalized[5]->value);
        $this->assertEquals(1, count($normalized[5]->orExpressions));

        $this->assertEquals('lastName', $normalized[5]->orExpressions[0]->propertyName);
        $this->assertEquals(QB::BEGINS_WITH, $normalized[5]->orExpressions[0]->operator);
        $this->assertEquals('Mac', $normalized[5]->orExpressions[0]->value);
    }

    /*public function testAddExpression()
    {
        $this->object->addExpression('>=', 'age', 'driverAge');
        $this->object->buildCriteria(['driverAge' => 17]);
        $criteria = $this->object->toArray();

        $this->assertArrayHasKey('age', $criteria, 'criteria contains entity property as key');
        $this->assertArrayHasKey('operator', $criteria['age'], 'criteria contains operator key');
        $this->assertArrayHasKey('value', $criteria['age'], 'criteria contains value key');
        $this->assertEquals('>=', $criteria['age']['operator'], 'criteria contains correct operator');
        $this->assertEquals(17, $criteria['age']['value'], 'criteria contains correct value');

        $this->object->addExpression('NOT IN', 'colour');
        $this->object->buildCriteria(['driverAge' => 17, 'colour'=>['red', 'yellow', 'blue']]);
        $criteria = $this->object->toArray();
        $this->assertEquals('NOT IN', $criteria['colour']['operator']);
        $this->assertEquals(['red', 'yellow', 'blue'], $criteria['colour']['value']);
        $this->assertNotSame(false, array_key_exists('age', $criteria));

        $this->object->addExpression('IS', 'nullValue');
        $this->object->buildCriteria(['driverAge' => 17, 'nullValue'=>'']);
        $criteria = $this->object->toArray();
        $this->assertEquals('IS', $criteria['nullValue']['operator']);
        $this->assertSame(null, $criteria['nullValue']['value']);
        $this->assertSame(true, array_key_exists('age', $criteria));
    }

    public function testAddLike()
    {
        $this->object->addExpression('>=', 'age', 'driverAge');
        $this->object->buildCriteria(['driverAge' => 17]);
        $criteria = $this->object->toArray();

        $this->assertArrayHasKey('age', $criteria, 'criteria contains entity property as key');
        $this->assertArrayHasKey('operator', $criteria['age'], 'criteria contains operator key');
        $this->assertArrayHasKey('value', $criteria['age'], 'criteria contains value key');
        $this->assertEquals('>=', $criteria['age']['operator'], 'criteria contains correct operator');
        $this->assertEquals(17, $criteria['age']['value'], 'criteria contains correct value');
    }

    public function testAddBeginsWith()
    {
        $this->object->addBeginsWith('firstName');
        $this->object->buildCriteria(['firstName' => 'P']);
        $criteria = $this->object->toArray();

        $this->assertArrayHasKey('firstName', $criteria, 'criteria contains entity property as key');
        $this->assertArrayHasKey('operator', $criteria['firstName'], 'criteria contains operator key');
        $this->assertArrayHasKey('value', $criteria['firstName'], 'criteria contains value key');
        $this->assertEquals('BEGINSWITH', $criteria['firstName']['operator'], 'criteria contains correct operator');
        $this->assertEquals('P', $criteria['firstName']['value'], 'criteria contains correct value');
    }

    public function testAddEndsWith()
    {
        $this->object->addEndsWith('firstName');
        $this->object->buildCriteria(['firstName' => 's']);
        $criteria = $this->object->toArray();

        $this->assertArrayHasKey('firstName', $criteria, 'criteria contains entity property as key');
        $this->assertArrayHasKey('operator', $criteria['firstName'], 'criteria contains operator key');
        $this->assertArrayHasKey('value', $criteria['firstName'], 'criteria contains value key');
        $this->assertEquals('ENDSWITH', $criteria['firstName']['operator'], 'criteria contains correct operator');
        $this->assertEquals('s', $criteria['firstName']['value'], 'criteria contains correct value');
    }

    public function testAddBetween()
    {
        $this->object->addBetween('age', 'lowerAge', 'upperAge');
        $this->object->buildCriteria(['lowerAge' => 17, 'upperAge' => 34]);
        $criteria = $this->object->toArray();

        $this->assertArrayHasKey('age', $criteria, 'criteria contains entity property as key');
        $this->assertArrayHasKey('operator', $criteria['age'], 'criteria contains operator key');
        $this->assertArrayHasKey('value', $criteria['age'], 'criteria contains value key');
        $this->assertEquals('BETWEEN', $criteria['age']['operator'], 'criteria contains correct operator');
        $this->assertEquals(17, $criteria['age']['value'], 'criteria contains correct value');
        $this->assertEquals(34, $criteria['age']['value2'], 'criteria contains correct value');
    }

    public function testAddContains()
    {
        $this->object->addContains('firstName');
        $this->object->buildCriteria(['firstName' => 's']);
        $criteria = $this->object->toArray();

        $this->assertArrayHasKey('firstName', $criteria, 'criteria contains entity property as key');
        $this->assertArrayHasKey('operator', $criteria['firstName'], 'criteria contains operator key');
        $this->assertArrayHasKey('value', $criteria['firstName'], 'criteria contains value key');
        $this->assertEquals('CONTAINS', $criteria['firstName']['operator'], 'criteria contains correct operator');
        $this->assertEquals('s', $criteria['firstName']['value'], 'criteria contains correct value');
    }*/
}
