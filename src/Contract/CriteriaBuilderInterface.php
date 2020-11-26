<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Query\CriteriaBuilder;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\FieldExpression;

interface CriteriaBuilderInterface
{
    /**
     * Specify first line of criteria (this is actually just an alias for andWhere, as they do the same thing)
     */
    public function where(string $propertyName, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Specify first line of criteria (this is actually just an alias for andWhereExpression, as they do the same thing)
     */
    public function whereExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Specify a line of criteria to join to the previous line with AND
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @return $this Returns $this to allow chained method calls
     */
    public function andWhere(string $propertyName, string $operator, $value): CriteriaBuilderInterface;

    public function andWhereExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Specify a line of criteria to join to the previous line with OR
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @return $this Returns $this to allow chained method calls
     */
    public function orWhere(string $propertyName, string $operator, $value): CriteriaBuilderInterface;

    public function orWhereExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface;

    public function andStart(): CriteriaBuilderInterface;

    public function orStart(): CriteriaBuilderInterface;

    public function andEnd(): CriteriaBuilderInterface;

    public function orEnd(): CriteriaBuilderInterface;

    public function end(): CriteriaBuilderInterface;

    /**
     * Return a standard array of CrteriaExpression objects, given a $criteria array that might contain a mixture
     * of old-style criteria arrays and CriteriaExpressions
     * @param array $criteria
     * @param string $pkProperty If criteria is just a list of IDs, specify the property that holds the ID
     * @return array
     */
    public function normalize(array $criteria, string $pkProperty = 'id'): array
}
