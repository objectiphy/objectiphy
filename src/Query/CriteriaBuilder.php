<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

/**
 * Helper class to build an array of criteria that can be passed to a repository find method.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class CriteriaBuilder
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

    protected array $expressions = [];
    protected array $params = [];
    protected array $translatedFieldNames = [];

    /** @var string $joinWith How we join our expressions with a parent Criteria Builder */
    private string $joinWith = 'AND';

    /**
     * Allows us to use CB::create() to chain method calls without having to assign to a variable first
     */
    public static function create(): CriteriaBuilder
    {
        return new CriteriaBuilder();
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andWhere, as they do the same thing)
     * @throws Exception\CriteriaException
     */
    public function where(
        string $propertyName,
        string $operator,
        $value,
        ?CriteriaBuilder $nestedCriteria = null,
        ?string $aggregateFunction = null,
        ?string $aggregateGroupByProperty = null
    ): CriteriaBuilder {
        return $this->andWhere($propertyName, $operator, $value, $nestedCriteria, $aggregateFunction, $aggregateGroupByProperty);
    }

    /**
     * Specify a line of criteria to join to the previous line with AND
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @param CriteriaBuilder|null $nestedCriteria Another criteria builder with one or more andWhere or orWhere
     * conditions already specified
     * @param string|null $aggregateFunction
     * @param string|null $aggregateGroupByProperty
     * @return $this Returns $this to allow chained method calls
     * @throws Exception\CriteriaException
     */
    public function andWhere(string $propertyName,
        string $operator,
        $value,
        ?CriteriaBuilder $nestedCriteria = null,
        ?string $aggregateFunction = null,
        ?string $aggregateGroupByProperty = null
    ): CriteriaBuilder {
        $this->buildExpression($propertyName, $operator, $value, $nestedCriteria, 'AND', $aggregateFunction, $aggregateGroupByProperty);

        return $this;
    }

    /**
     * Specify a line of criteria to join to the previous line with OR
     * @param string $propertyName Name of property on entity whose value is to be compared
     * @param string $operator Operator to compare with
     * @param mixed $value Value to compare against (array of values for IN, NOT IN, or BETWEEN) - or an alias (aliases
     * are strings prefixed with a colon : which act as the key to the value array passed into the build method)
     * @param CriteriaBuilder|null $nestedCriteria Another criteria builder with one or more andWhere or orWhere
     * conditions already specified
     * @param string|null $aggregateFunction
     * @param string|null $aggregateGroupByProperty
     * @return $this Returns $this to allow chained method calls
     * @throws Exception\CriteriaException
     */
    public function orWhere(
        string $propertyName, 
        string $operator, 
        $value, 
        ?CriteriaBuilder $nestedCriteria = null, 
        ?string $aggregateFunction = null, 
        ?string $aggregateGroupByProperty = null
    ): CriteriaBuilder {
        $this->buildExpression($propertyName, $operator, $value, $nestedCriteria, 'OR', $aggregateFunction, $aggregateGroupByProperty);

        return $this;
    }

    /**
     * Return an array of CriteriaExpression objects, optionally applying values
     * @param array $params Array of values, keyed on alias that was supplied when creating the criteria lines
     * @param boolean $removeUnbound Whether or not to remove expressions that have not been supplied with a value
     * @param bool $exceptionOnInvalidNull If value is null, and operator does not require null, whether to throw an exception
     * (if false, it will be converted to an empty string so as not to break the SQL).
     * @return array
     */
    public function build(
        array $params = [], 
        bool $removeUnbound = true, 
        bool $exceptionOnInvalidNull = true
    ): array {
        foreach ($this->expressions as $index=>$expression) {
            $expression->applyValues($params, false, $removeUnbound, $exceptionOnInvalidNull);
            if ($removeUnbound && $expression->hasUnboundParameters()) {
                $this->expressions[$index] = null;
            }
        }
        $this->expressions = array_values(array_filter($this->expressions));

        return $this->expressions;
    }

    /**
     * Build an order by array with the correct entity/property names based on a request that uses tokens.
     * For example, if the request comes in as:
     * {
     *     "orderBy": {
     *         "lastName": "DESC",
     *         "id": "ASC"
     *     }
     * }
     * ...you could pass in ['lastName'=>'DESC', 'id'=>'ASC'] as the $orderBy parameter, and
     * ['lastName'=>'customer.surname', 'id'=>'policy.id'] as the $tokenTranslations parameter, and
     * this method will return an orderBy array that you can use with Objectiphy:
     * ['customer.surname'=>'DESC', 'policy.id'=>'ASC'].
     * This is just a convenience method to save you having to build the array yourself. If you are re-using a criteria
     * builder that you used to build a criteria array, and the criteria included translated tokens, those translations
     * will be used in addition to any that you supply in $tokenTranslations ($tokenTranslations can be omitted if
     * that already covers all of your translation requirements).
     * @param array $orderBy The order by request (with tokens that might not match property names)
     * @param array $tokenTranslations Translations from tokens to property names
     * @param boolean $preserveUntranslated If some of the fields passed in do not have a translation, this option
     * specifies whether or not to preserve them (ie. return them as they are, without any translation). If false,
     * any such values will be omitted from the return value.
     * @return array
     */
    public function buildOrderBy(
        array $orderBy, 
        array $tokenTranslations = [], 
        bool $preserveUntranslated = true
    ): array {
        $this->translatedFieldNames = array_merge($this->translatedFieldNames, $tokenTranslations);

        return $this->getTranslatedFieldNames($orderBy, true, $preserveUntranslated);
    }
    
    /**
     * If the build method has been called, we can translate tokens into property names for all or specified fields.
     * This can be useful for quickly creating an orderBy array without having to translate all the field names again.
     * @param array|null $fields
     * @param bool $useKeys If used to create an orderBy array, and you have the field names as keys, and direction 
     * as values, setting this to true translates the field names in the keys and preserves the values (false will 
     * assume an indexed array and will translate field names in the values).
     * @param bool $preserveUntranslated If some of the fields passed in do not have a translation, this option
     * specifies whether or not to preserve them (ie. return them as they are, without any translation). If false,
     * any such values will be omitted from the return value.
     * @return array
     */
    protected function getTranslatedFieldNames(
        array $fields = null, 
        bool $useKeys = true, 
        bool $preserveUntranslated = true
    ): array {
        $translatedFieldNames = [];
        foreach ($this->translatedFieldNames as $key=>$translatedFieldName) {
            if (($useKeys && !empty($fields[$key])) || (!$useKeys && in_array($key, $fields))) {
                $fieldIndex = $fields ? array_search($key, $useKeys ? array_keys($fields) : $fields) : false;
                if ($fields === null || $fieldIndex !== false) {
                    $newIndex = $useKeys ? $translatedFieldName : count($translatedFieldNames);
                    $newValue = $useKeys ? $fields[$key] : $translatedFieldName;
                    $translatedFieldNames[$newIndex] = $newValue;
                }
            }
        }

        //Any fields passed in that do not have a translation, just use their original key/value
        if ($preserveUntranslated && $fields) {
            foreach ($fields as $key=>$value) {
                if (!isset($this->translatedFieldNames[$useKeys ? $key : $value])) {
                    $translatedFieldNames[$useKeys ? $key : count($translatedFieldNames)] = $value;
                }
            }
        }
        
        return $translatedFieldNames;
    }

    /**
     * Clear all expressions that have been added.
     */
    public function reset(): CriteriaBuilder
    {
        $this->expressions = [];
        $this->params = [];

        return $this;
    }

    /**
     * @return array
     */
    public function getAndExpressions(): array
    {
        return $this->joinWith == 'AND' ? $this->expressions : [];
    }

    /**
     * @return array
     */
    public function getOrExpressions(): array
    {
        return $this->joinWith == 'OR' ? $this->expressions : [];
    }

    /**
     * Return a standard array of CrteriaExpression objects, given a $criteria array that might contain a mixture
     * of old-style criteria arrays and CriteriaExpressions
     * @param array $criteria
     * @param string $pkProperty If criteria is just a list of IDs, specify the property that holds the ID
     * @return array
     * @throws Exception\CriteriaException
     */
    public function normalize(array $criteria, string $pkProperty = 'id'): array
    {
        $normalizedCriteria = [];

        //If $criteria is just a list of IDs, use primary key with IN
        $idCount = 0;
        foreach ($criteria as $critKey=>$critValue) {
            if (is_numeric($critKey) && intval($critKey) == $critKey && is_numeric($critValue) && intval($critValue) == $critValue) {
                $idCount++;
            } else {
                break;
            }
        }

        if ($idCount && $idCount == count($criteria)) {
            $expression = new CriteriaExpression($pkProperty, null, 'IN', array_values($criteria));
            $normalizedCriteria[] = $expression;
        } else {
            foreach ($criteria as $propertyName=>$expression) {
                if (!($expression instanceof CriteriaExpression)) {
                    $value = isset($expression['value']) ? $expression['value'] : $expression;
                    $expression = new CriteriaExpression(
                        $propertyName,
                        !empty($expression['alias']) ? $expression['alias'] : null,
                        !empty($expression['operator']) ? $expression['operator'] : ($value === null ? 'IS' : '='),
                        $value,
                        !empty($expression['alias2']) ? $expression['alias2'] : null,
                        isset($expression['value2']) ? $expression['value2'] : null,
                        !empty($expression['and']) ? $this->normalize($expression['and']) : [],
                        !empty($expression['or']) ? $this->normalize($expression['or']) : [],
                        !empty($expression['aggregateFunction']) ? $expression['aggregateFunction'] : null,
                        !empty($expression['aggregateGroupByProperty']) ? $expression['aggregateGroupByProperty'] : null
                    );
                }
                $normalizedCriteria[] = $expression;
            }
        }

        return $normalizedCriteria;
    }
    
    /**
     * @param $propertyName
     * @param $operator
     * @param $values
     * @param CriteriaBuilder|null $nestedCriteria
     * @param string $joinWith
     * @throws Exception\CriteriaException
     */
    private function buildExpression(
        string $propertyName, 
        string $operator, 
        $values, 
        ?CriteriaBuilder $nestedCriteria = null, 
        string $joinWith = 'AND', 
        ?string $aggregateFunction = null, 
        ?string $aggregateGroupByProperty = null
    ): void {
        $args = $this->getAliasesAndValues($values, in_array($operator, [self::IN, self::NOT_IN]));
        $expression = new CriteriaExpression(
            $propertyName,
            $args[self::ALIAS],
            $operator,
            $args[self::VALUE],
            $args[self::ALIAS_2],
            $args[self::VALUE_2],
            $nestedCriteria ? $nestedCriteria->getAndExpressions() : [],
            $nestedCriteria ? $nestedCriteria->getOrExpressions() : [],
            $aggregateFunction,
            $aggregateGroupByProperty
        );
        $this->translatedFieldNames[$args[self::ALIAS]] = $propertyName; //Doesn't matter if alias is empty
        $this->translatedFieldNames[$args[self::ALIAS_2]] = $propertyName;
        if (!empty($this->expressions)) {
            $parentExpression = $this->expressions[count($this->expressions) - 1];
            if ($joinWith == 'OR') {
                $parentExpression->orWhere($expression);
            } else {
                //If parent is at the top, add as a sibling, so that unbound values can be removed without losing chained 'ands' that do have a value
                if (!$parentExpression->parentExpression) {
                    $this->expressions[] = $expression;
                } else {
                    $parentExpression->andWhere($expression);
                }
            }
        } else {
            $this->expressions[] = $expression;
            $this->joinWith = $joinWith;
        }
    }

    private function getAliasesAndValues($values, bool $valueIsArray = false): array
    {
        $values = !$valueIsArray && (is_array($values) || $values instanceof \Traversable) ? $values : [$values];
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

    /**
     * Convert the CriteriaExpression objects into an array of criteria (only used by unit tests).
     * @return array
     */
    public function toArray(): array
    {
        $arrayExpressions = [];
        foreach ($this->expressions as $expression) {
            if ($expression) {
                if (isset($arrayExpressions[$expression->propertyName])) {
                    //Rather than overwrite an existing one, add this as an andExpression
                    $arrayExpressions[$expression->propertyName]['andExpressions'][] = $expression->toArray();
                } else {
                    $arrayExpressions[$expression->propertyName] = $expression->toArray();
                }
            }
        }

        return $arrayExpressions;
    }
}
