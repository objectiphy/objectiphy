<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Query\FieldExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface CriteriaBuilderInterface
{
    /**
     * Specify a line of criteria to join to the previous line with AND
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function and(string $propertyName, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Specify a line of criteria to join the previous line with AND using a FieldExpression object
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function andExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Specify a line of criteria to join to the previous line with OR
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function or(string $propertyName, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Specify a line of criteria to join to the previous line with OR using a FieldExpression object
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function orExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface;

    /**
     * Start an AND group (ie. AND followed by an open bracket).
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function andStart(): CriteriaBuilderInterface;

    /**
     * Start an OR group (ie. OR followed by an open bracket).
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function orStart(): CriteriaBuilderInterface;

    /**
     * End a group - alias for end
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function andEnd(): CriteriaBuilderInterface;

    /**
     * End a group - alias for end
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function orEnd(): CriteriaBuilderInterface;

    /**
     * End an AND or OR group (ie. close bracket).
     * @return CriteriaBuilderInterface Returns $this to allow chained method calls
     */
    public function end(): CriteriaBuilderInterface;

    /**
     * Return a standard array of CriteriaExpression objects, given a $criteria array that might contain a mixture
     * of old-style criteria arrays and CriteriaExpressions
     * @param array $criteria
     * @param string $pkProperty If criteria is just a list of IDs, specify the property that holds the ID
     * @return array
     */
    public function normalize(array $criteria, string $pkProperty = 'id'): array;
}
