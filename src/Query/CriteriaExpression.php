<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;
use Objectiphy\Objectiphy\Exception\CriteriaException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * Represents a line of criteria to filter on, optionally with a collection of child (nested) criteria lines
 * that are joined together with AND or OR.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class CriteriaExpression implements \JsonSerializable, QueryPartInterface, PropertyPathConsumerInterface
{
    public FieldExpression $property;
    public string $operator;
    public $value;
    public ?string $alias = '';
    public $value2;
    public ?string $alias2;
    /**
     * @var CriteriaExpression[]
     */
    public array $andExpressions = [];
    /**
     * @var CriteriaExpression[]
     */
    public array $orExpressions = [];
    public CriteriaExpression $parentExpression;
    

    /**
     * @param FieldExpression $property The property being filtered on.
     * @param string $operator Operator to use (normal MySQL operators, plus special: BEGINSWITH, ENDSWITH, and
     * CONTAINS).
     * @param mixed $value Optionally supply the value to filter by up-front (if not using the applyValues method to set
     * all the values in one go).
     * @param string|null $alias If supplying values in an array to the applyValues method, and the array key does not
     * match the property name, you can specify an alias.
     * @param mixed $value2 For BETWEEN operator, a second value is required, and can be supplied up-front here if not
     * using the applyValues method.
     * @param string|null $alias2 If operator takes more than one value (BETWEEN), and values are being supplied via the
     * applyValues method, specify the array key that holds the second value.
     * @param array $andExpressions An array of CriteriaExpression objects that should be ANDed together with this one.
     * @param array $orExpressions An array of CritieraExpression objects that should be ORed together with this one.
     * @throws CriteriaException
     */
    public function __construct(
        $property,
        ?string $alias = null,
        string $operator = '=',
        $value = null,
        ?string $alias2 = null,
        $value2 = null,
        array $andExpressions = [],
        array $orExpressions = []
    ) {
        if (is_string($property)) {
            $this->property = new FieldExpression($property);
        } elseif ($property instanceof FieldExpression) {
            $this->property = $property;
        } else {
            throw new QueryException('Property of a CriteriaExpression must be a string or a FieldExpression object.');
        }
        $this->operator = strtoupper($operator);
        $this->alias = $alias;
        $this->value = $value;
        $this->alias2 = $alias2;
        $this->value2 = $value2;
        $this->populateAnds($andExpressions);
        $this->populateOrs($orExpressions);
        $this->validate();
    }

    private function populateAnds(array $andExpressions): void
    {
        foreach ($andExpressions as $andExpression) {
            $andExpression = $andExpression instanceof CriteriaExpression
                ? $andExpression
                : new CriteriaExpression(...$andExpression);
            $andExpression->parentExpression = $this;
            $this->andExpressions[] = $andExpression;
        }
    }

    private function populateOrs(array $orExpressions): void
    {
        foreach ($orExpressions as $orExpression) {
            $orExpression = $orExpression instanceof CriteriaExpression
                ? $orExpression
                : new CriteriaExpression(...$orExpression);
            $orExpression->parentExpression = $this;
            $this->orExpressions[] = $orExpression;
        }
    }

    /**
     * This method can be used to specify all of the values to filter on in one go.
     * @param $values
     * @param bool $overwrite If true, any existing values (eg. specified when the constructor was called) will be
     * overwritten with the new ones.
     * @param bool $removeUnbound If there are any aliases that have not been given a value in the values array,
     * the corresponding expression can be removed (useful where optional filters are being applied).
     * @param bool $exceptionOnInvalidNull If value is null, and operator does not require null, whether to throw an
     * exception (if false, it will be converted to an empty string so as not to break the query).
     */
    public function applyValues(
        $values,
        bool $overwrite = false,
        bool $removeUnbound = true,
        bool $exceptionOnInvalidNull = true
    ): void {
        if (!$values && $this->operator) {
            $this->dealWithNulls('value', $exceptionOnInvalidNull);
        } else {
            foreach ($values as $key => $value) {
                if ($this->alias == $key) {
                    $this->value = empty($this->value) || $overwrite ? ($this->requireNull() ? null : $value) : $this->value;
                    $this->dealWithNulls('value', $exceptionOnInvalidNull);
                    $this->alias = null;
                } elseif ($this->alias2 == $key) {
                    $this->value2 = empty($this->value2) || $overwrite ? ($this->requireNull() ? null : $value) : $this->value2;
                    $this->dealWithNulls('value2', $exceptionOnInvalidNull);
                    $this->alias2 = null;
                } elseif ((string) $this->property == $key) {
                    $this->value = empty($this->value) || $overwrite ? ($this->requireNull() ? null : $value) : $this->value;
                    $this->dealWithNulls('value', $exceptionOnInvalidNull);
                } elseif (is_array($this->value)) {
                    foreach ($this->value as $index => $inValue) {
                        if (substr(strval($inValue), 0, 1) == ':' && substr(strval($inValue), 1) == $key) {
                            $this->value[$index] = $value;
                        }
                    }
                }
            }
        }
        
        foreach ($this->andExpressions as $andIndex=>$andExpression) {
            $andExpression->applyValues($values, $overwrite, $removeUnbound, $exceptionOnInvalidNull);
            if ($removeUnbound && $andExpression->hasUnboundParameters()) {
                $this->andExpressions[$andIndex] = null;
            }
        }
        $this->andExpressions = array_values(array_filter($this->andExpressions));
        
        foreach ($this->orExpressions as $orIndex=>$orExpression) {
            $orExpression->applyValues($values, $overwrite, $removeUnbound, $exceptionOnInvalidNull);
            if ($removeUnbound && $orExpression->hasUnboundParameters()) {
                $this->orExpressions[$orIndex] = null;
            }
        }
        $this->orExpressions = array_values(array_filter($this->orExpressions));
    }

    /**
     * @return bool
     */
    public function hasUnboundParameters(): bool
    {
        if ($this->operator != QB::IS && $this->operator != QB::IS_NOT
            && (($this->alias && $this->value === null) || ($this->alias2 && $this->value2 === null))
        ) {
            return true;
        } elseif (is_array($this->value)) {
            foreach ($this->value as $index=>$value) {
                if (substr(strval($value), 0, 1) == ':') {
                    return true;
                }
            }
        }

        return false;
    }
    
    /**
     * @return array|bool|float|int|string|null
     */
    public function getCriteriaValue()
    {
        return $this->convertValue($this->value);
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function getCriteriaValue2()
    {
        return $this->convertValue($this->value2);
    }

    /**
     * Add a child expression to join with this one using AND.
     * @param CriteriaExpression|array $andExpression
     * @return $this
     * @throws CriteriaException
     */
    public function andWhere($andExpression): CriteriaExpression
    {
        if (is_array($andExpression)) {
            $andExpression = new CriteriaExpression(...$andExpression);
        }

        if ($andExpression instanceof CriteriaExpression) {
            $andExpression->parentExpression = $this;
            $this->andExpressions[] = $andExpression;
        } else {
            throw new CriteriaException("Invalid 'AND' expression");
        }

        return $this;
    }

    /**
     * Add a child expression to join with this one using OR.
     * @param CriteriaExpression|array $orExpression
     * @return $this
     * @throws CriteriaException
     */
    public function orWhere($orExpression): CriteriaExpression
    {
        if (is_array($orExpression)) {
            $orExpression = new CriteriaExpression(...$orExpression);
        }

        if ($orExpression instanceof CriteriaExpression) {
            $orExpression->parentExpression = $this;
            $this->orExpressions[] = $orExpression;
        } else {
            throw new CriteriaException("Invalid 'OR' expression");
        }

        return $this;
    }

    /**
     * @return string Convert 'special' operators to something MySQL will understand.
     */
    public function getCriteriaOperator(): string
    {
        switch (strtoupper($this->operator)) {
            case 'BEGINSWITH':
            case 'ENDSWITH':
            case 'CONTAINS':
                return 'LIKE';
            default:
                return strtoupper($this->operator);
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $encoded = json_encode($this);
        $decoded = json_decode($encoded, true);
        
        return $decoded;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        //Remove recursion
        $copy = clone($this);
        unset($copy->parentExpression);
        
        return get_object_vars($copy);
    }

    /**
     * @return bool Whether or not any of the criteria (including nested and/or criteria) makes reference to an
     * aggregate function.
     */
    public function hasAggregateGroup(): bool
    {
        if ($this->property->aggregateGroupByProperty) {
            return true;
        } elseif ($this->value instanceof FieldExpression && $this->value->aggregateGroupByProperty) {
            return true;
        } elseif ($this->value2 instanceof FieldExpression && $this->value2->aggregateGroupByProperty) {
            return true;
        }

        foreach ($this->andExpressions as $andExpression) {
            if ($andExpression->hasAggregateGroup()) {
                return true;
            }
        }

        foreach ($this->orExpressions as $orExpression) {
            if ($orExpression->hasAggregateGroup()) {
                return true;
            }
        }
        
        return false;
    }

    public function getPropertyPaths(): array
    {
        $paths = $this->property->getPropertyPaths();
        if ($this->value instanceof FieldExpression) {
            $paths = array_merge($paths, $this->value->getPropertyPaths());
        }
        if ($this->value2 instanceof FieldExpression) {
            $paths = array_merge($paths, $this->value2->getPropertyPaths());
        }

        return $paths;
    }

    public function finalise(MappingCollection $mappingCollection, ?string $className = null)
    {
        //Not sure there is anything to do here...?
    }

    /**
     * Return query with parameters resolved (for display only, NOT for execution!)
     * @return string
     */
    public function __toString(): string
    {
        $params = [];
        $queryString = $this->toString($params);
        foreach ($params as $key => $value) {
            $queryString = str_replace(':' . $key, "'" . $value . "'", $queryString);
        }

        return $queryString;
    }

    /**
     * Return parameterised query
     * @param array $params
     * @return string
     */
    public function toString(array &$params = [])
    {
        $string = $this->property . ' ' . $this->operator . ' ';
        if (is_array($this->value)) {
            $string .= '(';
        }
        $values = is_array($this->value) ? $this->value : [$this->value];
        $stringValues = [];
        foreach ($values as $value) {
            if ($value === null) {
                $stringValues[] = 'null';
            } else {
                $paramCount = count($params) + 1;
                $params['param_' . $paramCount] = $this->convertValue($value);
                $stringValues[] = ':param_' . $paramCount;
            }
        }
        $string .= implode(',', $stringValues);
        if (is_array($this->value)) {
            $string .= ')';
        }
        if ($this->operator == QB::BETWEEN) {
            $string .= ' AND ';
            if ($this->value2 === null) {
                $string .= 'null';
            } else {
                $paramCount = count($params) + 1;
                $params['param_' . $paramCount] = $this->convertValue($this->value2);
                $string .= ':param_' . $paramCount;
            }
        }
        
        foreach ($this->andExpressions as $andExpression) {
            $string .= ' AND (' . $andExpression->toString($params) . ')';
        }
        foreach ($this->orExpressions as $orExpression) {
            $string .= ' OR (' . $orExpression->toString($params) . ')';
        }

        return $string;
    }

    private function dealWithNulls($property, $exceptionOnInvalidNull): void
    {
        if ($this->$property === null && !$this->requireNull()) {
            if ($exceptionOnInvalidNull) {
                throw new CriteriaException("Warning! Operator '" . $this->operator
                    . "' for criteria expression '" . strval($this) . "' does not support NULL values. "
                    . "This exception can be suppressed by passing false to the \$exceptionOnInvalidNull "
                    . "parameter when building the criteria. NOTE: If suppressing this exception, the "
                    . "value will be converted to an empty string: ''.");
            } else {
                $this->$property = '';
            }
        }
    }

    /**
     * @return bool Whether or not the operator we are using requires the value to be null.
     */
    private function requireNull(): bool
    {
        return $this->operator == QB::IS || $this->operator == QB::IS_NOT;
    }

    /**
     * Convert values as required by special operators.
     * @param $value
     * @return array|bool|float|int|null|string
     */
    private function convertValue($value)
    {
        $scalarValue = $this->getScalarValue($value, in_array(strtoupper($this->operator), ['IN', 'NOT IN']));

        switch ($this->operator) {
            case 'IS':
            case 'IS NOT':
                return null;
            case 'BEGINSWITH':
                return $scalarValue . '%';
            case 'ENDSWITH':
                return '%' . $scalarValue;
            case 'CONTAINS':
                return '%' . $scalarValue . '%';
            default:
                return $scalarValue;
        }
    }

    /**
     * Ensure the value we are filtering on is scalar, so that MySQL can understand it.
     * @param $value
     * @param bool $allowArray
     * @return array|bool|float|int|null|string
     */
    private function getScalarValue($value, bool $allowArray = true)
    {
        $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        $value = ($allowArray && is_array($value)) || is_scalar($value) ? $value : null;

        //If arrays are allowed, ensure they only contain scalar values, and no nested arrays
        if (!$allowArray && is_array($value)) {
            $value = null;
        } elseif (is_array($value)) {
            foreach ($value as $index=>$element) {
                $value[$index] = $this->getScalarValue($element, false);
            }
        }

        return $value;
    }

    /**
     * Make sure we have a valid object - throw exception if not.
     * @throws CriteriaException If the operator is not supported, or the relevant alias and/or value for the
     * second BETWEEN value is not supplied.
     */
    private function validate(): void
    {
        if (!$this->validateAggregateFunction()) {
            throw new CriteriaException("Aggregate function '$this->aggregateFunction' is not supported.");
        }
        if (!$this->validateOperator()) {
            throw new CriteriaException("Operator '$this->operator' is not supported.");
        }

        if (strtoupper($this->operator) == 'BETWEEN') {
            if (!$this->alias2 && $this->value2 !== null) {
                //We can just make up an alias - all it will be used for is the named parameter in the prepared query
                $this->alias2 = 'alias_' . uniqid();
            } elseif ($this->alias2 === null) {
                throw new CriteriaException('alias2 is required for between operator (unless you also supply value2 up-front)');
            }
        }

        foreach ($this->andExpressions as $andExpression) {
            $andExpression->validate();
        }
        foreach ($this->orExpressions as $orExpression) {
            $orExpression->validate();
        }
    }

    /**
     * @return bool Whether or not the operator is supported.
     */
    private function validateOperator(): bool
    {
        $valid = in_array(strtoupper($this->operator), [
            '=', '>', '>=', '<', '<=', '<>', '!=',
            'BETWEEN', 'IN', 'NOT IN', 'IS', 'IS NOT',
            'LIKE', 'BEGINSWITH', 'ENDSWITH', 'CONTAINS'
        ]);

        return $valid;
    }

    /**
     * @return bool Whether or not the operator is supported.
     */
    private function validateAggregateFunction(): bool
    {
        $valid = in_array(strtoupper($this->aggregateFunction ?? ''), [
            '', 'AVG', 'COUNT', 'MAX', 'MIN', 'STD',
            'STDDEV', 'SUM', 'VARIANCE',
        ]);

        return $valid;
    }
}
