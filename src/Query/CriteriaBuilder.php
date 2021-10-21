<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaBuilderInterface;
use Objectiphy\Objectiphy\Exception\QueryException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Build criteria used for WHERE and ON clauses, including nested conditions.
 */
class CriteriaBuilder implements CriteriaBuilderInterface
{
    public const EQ = '=';
    public const EQUALS = self::EQ; //Alias
    public const NOT_EQ = '!=';
    public const NOT_EQUALS = self::NOT_EQ;
    public const GT = '>';
    public const GREATER_THAN = self::GT;
    public const GTE = '>=';
    public const GREATER_THAN_OR_EQUAL_TO = self::GTE;
    public const LT = '<';
    public const LESS_THAN = self::LT;
    public const LTE = '<=';
    public const LESS_THAN_OR_EQUAL_TO = self::LTE;
    public const IN = 'IN';
    public const NOT_IN = 'NOT IN';
    public const IS = 'IS';
    public const IS_NOT = 'IS NOT';
    public const BETWEEN = 'BETWEEN';
    public const BEGINS_WITH = 'BEGINSWITH';
    public const ENDS_WITH = 'ENDSWITH';
    public const CONTAINS = 'CONTAINS';
    public const LIKE = 'LIKE';

    private const ALIAS = 0;
    private const VALUE = 1;
    private const ALIAS_2 = 2;
    private const VALUE_2 = 3;
    
    /**
     * @var array The criteria array we are currently adding lines to
     */
    protected array $currentCriteriaCollection;

    /**
     * @var int Keeps track of how deep we are when using nested criteria
     */
    protected int $groupNestingLevel = 0;

    /**
     * Specify a line of criteria to join to the previous line with AND
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @return $this Returns $this to allow chained method calls
     * @throws QueryException
     */
    public function and(string $propertyName, string $operator, $value): CriteriaBuilderInterface
    {
        $fieldExpression = new FieldExpression($propertyName);
        $this->andExpression($fieldExpression, $operator, $value);

        return $this;
    }

    /**
     * Join an expression with AND.
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return CriteriaBuilderInterface
     * @throws QueryException
     */
    public function andExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface
    {
        $joiner = CriteriaExpression::JOINER_AND;
        $this->currentCriteriaCollection[] = $this->buildExpression($joiner, $expression, $operator, $value);
        
        return $this;
    }

    /**
     * Specify a line of criteria to join to the previous line with OR
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @return $this Returns $this to allow chained method calls
     * @throws QueryException
     */
    public function or(string $propertyName, string $operator, $value): CriteriaBuilderInterface
    {
        $fieldExpression = new FieldExpression($propertyName);
        $this->orExpression($fieldExpression, $operator, $value);

        return $this;
    }

    /**
     * Join an expression with OR.
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return CriteriaBuilderInterface
     */
    public function orExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface
    {
        $joiner = CriteriaExpression::JOINER_OR;
        $this->currentCriteriaCollection[] = $this->buildExpression($joiner, $expression, $operator, $value);

        return $this;
    }

    /**
     * Start an AND group - ie. AND followed by an open bracket.
     * @return CriteriaBuilderInterface
     */
    public function andStart(): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection[] = new CriteriaGroup(CriteriaGroup::GROUP_TYPE_START_AND);
        $this->groupNestingLevel++;

        return $this;
    }

    /**
     * Start an OR group - ie. OR followed by an open bracket.
     * @return CriteriaBuilderInterface
     */
    public function orStart(): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection[] = new CriteriaGroup(CriteriaGroup::GROUP_TYPE_START_OR);
        $this->groupNestingLevel++;

        return $this;
    }

    /**
     * Alias for end.
     * @return CriteriaBuilderInterface
     */
    public function andEnd(): CriteriaBuilderInterface
    {
        $this->end();
        return $this;
    }

    /**
     * Alias for end.
     * @return CriteriaBuilderInterface
     */
    public function orEnd(): CriteriaBuilderInterface
    {
        $this->end();
        return $this;
    }

    /**
     * End an AND or OR group - ie. close bracket.
     * @return CriteriaBuilderInterface
     */
    public function end(): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection[] = new CriteriaGroup(CriteriaGroup::GROUP_TYPE_END);
        $this->groupNestingLevel--;

        return $this;
    }

    /**
     * Return a standard array of CrteriaExpression objects, given a $criteria array that might contain a mixture
     * of plain criteria arrays and CriteriaExpressions
     * @param array $criteria
     * @param string $pkProperty If criteria is just a list of IDs, specify the property that holds the ID
     * @return array
     * @throws QueryException
     */
    public function normalize(array $criteria, string $pkProperty = 'id'): array
    {
        $normalizedCriteria = [];

        //If $criteria is just a list of IDs, use primary key with IN
        $idCount = 0;
        foreach ($criteria as $critKey => $critValue) {
            if (is_numeric($critKey) && intval($critKey) == $critKey && is_numeric($critValue) && intval($critValue) == $critValue) {
                $idCount++;
            } else {
                break;
            }
        }

        if ($idCount && $idCount == count($criteria)) {
            $expression = new CriteriaExpression(new FieldExpression($pkProperty), null, 'IN', array_values($criteria));
            $normalizedCriteria[] = $expression;
        } else {
            foreach ($criteria as $propertyName => $expression) {
                if (!($expression instanceof CriteriaExpression) && !($expression instanceof JoinExpression)) {
                    $isArray = is_array($expression);
                    $value = $isArray && array_key_exists('value', $expression) ? $expression['value'] : $expression;
                    $defaultOperator = $value === null ? 'IS' : '=';
                    $expression = new CriteriaExpression(
                        new FieldExpression($propertyName),
                        $isArray ? ($expression['alias'] ?? null) : null,
                        $isArray ? ($expression['operator'] ?? $defaultOperator) : $defaultOperator,
                        $value,
                        $isArray ? ($expression['alias2'] ?? null) : null,
                        $isArray ? ($expression['value2'] ?? null) : null
                    );
                }
                $normalizedCriteria[] = $expression;
            }
        }

        return $normalizedCriteria;
    }

    /**
     * Convert the CriteriaExpression objects into an array of criteria (only used by unit tests).
     * @return array
     */
    public function toArray(): array
    {
        $arrayExpressions = [];
        foreach ($this->currentCriteriaCollection as $expression) {
            if ($expression) {
                $arrayExpressions[$expression->property->getExpression()] = $expression->toArray();
            }
        }

        return $arrayExpressions;
    }

    /**
     * Apply values to placeholder tokens, optionally removing any lines of criteria that still have unbound values.
     * @param array $collection
     * @param array $params
     * @param bool $removeUnbound
     */
    protected function applyValues(array &$collection, array $params, bool $removeUnbound = true): void
    {
        foreach ($collection as $index => $expression) {
            if ($expression instanceof CriteriaExpression) {
                $expression->applyValues($params, false, $removeUnbound);
                if ($removeUnbound && $expression->hasUnboundParameters()) {
                    $collection[$index] = null;
                }
            }
        }
        $collection = array_values(array_filter($collection));
    }

    /**
     * Create a CriteriaExpression object for the given parts.
     * @param string $joiner
     * @param FieldExpression $property
     * @param string $operator
     * @param $values
     * @return CriteriaExpression
     * @throws QueryException
     */
    private function buildExpression(
        string $joiner,
        FieldExpression $property,
        string $operator,
        $values
    ): CriteriaExpression {
        $args = $this->getAliasesAndValues($values, in_array($operator, [self::IN, self::NOT_IN]));
        $expression = new CriteriaExpression(
            $property,
            $args[self::ALIAS],
            $operator,
            $args[self::VALUE],
            $args[self::ALIAS_2],
            $args[self::VALUE_2]
        );
        $expression->joiner = $joiner;

        return $expression;
    }

    /**
     * Split out values into actual values and aliases.
     * @param $values
     * @param bool $valueIsArray
     * @return array
     */
    private function getAliasesAndValues($values, bool $valueIsArray = false): array
    {
        $values = !$valueIsArray && (is_iterable($values)) ? $values : [$values];
        if (!array_key_exists(0, $values)) {
            $stop = true;
        }
        $usingAlias = is_string($values[0]) && substr($values[0], 0, 1) == ':';
        $alias = $usingAlias ? substr($values[0], 1) : null;
        $value = $usingAlias ? null : $values[0];

        $alias2 = null;
        $value2 = null;
        if (isset($values[1])) {
            $usingAlias2 = is_string($values[1]) && substr($values[1], 0, 1) == ':';
            $alias2 = $usingAlias2 ? substr($values[1], 1) : null;
            $value2 = $usingAlias2 ? null : $values[1];
        }

        return [$alias, $value, $alias2, $value2];
    }
}
