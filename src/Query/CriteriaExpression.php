<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;
use Objectiphy\Objectiphy\Exception\CriteriaException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * Represents a line of criteria to filter on.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class CriteriaExpression implements CriteriaPartInterface, JoinPartInterface, PropertyPathConsumerInterface, \JsonSerializable
{
    public const JOINER_AND = 'AND';
    public const JOINER_OR = 'OR';

    public string $joiner = self::JOINER_AND;
    public FieldExpression $property;
    public string $operator;
    public $value;
    public ?string $alias = '';
    public $value2;
    public ?string $alias2;
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
     * @throws CriteriaException
     */
    public function __construct(
        $property,
        ?string $alias = null,
        string $operator = '=',
        $value = null,
        ?string $alias2 = null,
        $value2 = null
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
        $this->validate();
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
        ?array $values,
        bool $overwrite = false,
        bool $removeUnbound = true,
        bool $exceptionOnInvalidNull = true
    ): void {
        if (!$values && $this->operator) {
            $this->checkNullValidity('value', $exceptionOnInvalidNull);
        } else {
            foreach ($values as $key => $value) {
                if ($this->alias == $key) {
                    $this->value = empty($this->value) || $overwrite ? ($this->requireNull() ? null : $value) : $this->value;
                    $this->checkNullValidity('value', $exceptionOnInvalidNull);
                    $this->alias = null;
                } elseif ($this->alias2 == $key) {
                    $this->value2 = empty($this->value2) || $overwrite ? ($this->requireNull() ? null : $value) : $this->value2;
                    $this->checkNullValidity('value2', $exceptionOnInvalidNull);
                    $this->alias2 = null;
                } elseif ((string) $this->property == $key) {
                    $this->value = empty($this->value) || $overwrite ? ($this->requireNull() ? null : $value) : $this->value;
                    $this->checkNullValidity('value', $exceptionOnInvalidNull);
                } elseif (is_array($this->value)) {
                    foreach ($this->value as $index => $inValue) {
                        if (substr(strval($inValue), 0, 1) == ':' && substr(strval($inValue), 1) == $key) {
                            $this->value[$index] = $value;
                        }
                    }
                }
            }
        }
    }

    /**
     * Whether or not there are any criteria values in the query that have parameter placeholders for which
     * no value has yet been supplied.
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
     * @return array
     */
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
    public function toString(array &$params = []): string
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
                $params['param_' . $paramCount] = $value;
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
                $params['param_' . $paramCount] = $this->value2;
                $string .= ':param_' . $paramCount;
            }
        }

        return $string;
    }

    /**
     * If value is null, and operator does not support null, either reject it, or convert to empty string.
     * @param $property
     * @param bool $exceptionOnInvalidNull Whether or not to reject invalid null assignments.
     */
    private function checkNullValidity(string $property, bool $exceptionOnInvalidNull): void
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
     * Make sure we have a valid object - throw exception if not.
     * @throws CriteriaException If the operator is not supported, or the relevant alias and/or value for the
     * second BETWEEN value is not supplied.
     */
    private function validate(): void
    {
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
}
