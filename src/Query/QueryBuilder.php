<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaBuilderInterface;
use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Exception\QueryException;

/**
 * Helper class to build an array of criteria that can be passed to a repository find method.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class QueryBuilder extends CriteriaBuilder implements CriteriaBuilderInterface
{
    /**
     * @var FieldExpression[]
     */
    private array $select = [];

    private string $from = '';

    /**
     * @var JoinPartInterface[]
     */
    private array $joins = [];

    /**
     * @var CriteriaPartInterface[]
     */
    private array $where = [];

    /**
     * @var FieldExpression[]
     */
    private array $groupBy = [];

    /**
     * @var CriteriaPartInterface[]
     */
    private array $having = [];

    /**
     * @var FieldExpression[]
     */
    private array $orderBy = [];

    private ?int $limit = null;
    private ?int $offset = null;

    private array $params = [];

    /**
     * Not a singleton (no private constructor): this static method allows us to use QB::create() to chain
     * method calls without having to assign to a variable first.
     */
    public static function create(): QueryBuilder
    {
        $qb = new QueryBuilder();

        return $qb;
    }

    public function __construct()
    {
        $this->currentCriteriaCollection =& $this->where;
    }

    public function select(string ...$fields): QueryBuilder
    {
        foreach ($fields as $field) {
            $fieldExpression = new FieldExpression($field, false);
            $this->select[] = $field;
        }

        return $this;
    }

    public function from(string $className): QueryBuilder
    {
        $this->from = $className;
        return $this;
    }

    public function leftJoin($targetEntityClassName, $alias): QueryBuilder
    {
        $this->addJoin($targetEntityClassName, $alias, JoinExpression::JOIN_TYPE_LEFT);

        return $this;
    }

    public function innerJoin($targetEntityClassName, $alias): QueryBuilder
    {
        $this->addJoin($targetEntityClassName, $alias, JoinExpression::JOIN_TYPE_INNER);

        return $this;
    }

    public function on(string $propertyName, string $operator, $value): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection = $this->joins;
        return $this->and($propertyName, $operator, $value);
    }

    public function onExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection = $this->joins;
        return $this->andExpression($expression, $operator, $value);
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andWhere, as they do the same thing)
     * @throws QueryException
     */
    public function where(string $propertyName, string $operator, $value): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection = $this->where;
        return $this->and($propertyName, $operator, $value);
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andWhereExpression, as they do the same thing)
     * @throws QueryException
     */
    public function whereExpression(FieldExpression $expression, string $operator, $value): CriteriaBuilderInterface
    {
        $this->currentCriteriaCollection = $this->where;
        return $this->andExpression($expression, $operator, $value);
    }

    public function groupBy(string ...$propertyNames): QueryBuilder
    {
        foreach ($propertyNames as $propertyName) {
            $this->groupBy[] = new FieldExpression($propertyName, true);
        }

        return $this;
    }

    /**
     * Specify first line of having criteria (this is actually just an alias for andHaving, as they do the same thing)
     * @throws QueryException
     */
    public function having(string $propertyName, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection = $this->having;
        return $this->and($propertyName, $operator, $value);
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andHavingExpression, as they do the same thing)
     * @throws QueryException
     */
    public function havingExpression(FieldExpression $expression, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection = $this->having;
        return $this->andExpression($expression, $operator, $value);
    }

    public function orderBy(array $propertyNames): QueryBuilder
    {
        foreach ($propertyNames as $key => $value) {
            if (is_string($key) && in_array($value, 'ASC', 'DESC', 'asc', 'desc')) {
                $fieldExpression = new FieldExpression($key . ' ' . strtoupper($value), false);
            } elseif (is_int($key) && is_string($value)) {
                $fieldExpression = new FieldExpression($value . ' ASC');
            } else {
                throw new QueryException('Invalid orderBy properties. Please use property name as the key and direction as the value, or a numeric key and property name as the value (which defaults to ASC for direction)');
            }
        }

        return $this;
    }

    public function limit(int $value): QueryBuilder
    {
        $this->limit = $value;
        return $this;
    }

    public function offset(int $value): QueryBuilder
    {
        $this->offset = $value;
        return $this;
    }

    public function buildSelectQuery(array $params = [], bool $removeUnbound = true): SelectQuery
    {
        $this->applyValues($this->where, $params, $removeUnbound);
        $this->applyValues($this->having, $params, $removeUnbound);
        $this->applyValues($this->joins, $params, $removeUnbound);

        //TODO: Check we have valid info? Eg. that we don't have a JOIN without an ON
        
        $query = new SelectQuery();
        $query->setSelect($this->select);
        $query->setFrom($this->from);
        $query->setJoins($this->joins);
        $query->setWhere($this->where);
        $query->setGroupBy($this->groupBy);
        $query->setHaving($this->having);
        $query->setOrderBy($this->orderBy);
        $query->setLimit($this->limit);
        $query->setOffset($this->offset);

        return $query;
    }

//    /**
//     * Return an array of CriteriaExpression objects, optionally applying values
//     * @param array $params Array of values, keyed on alias that was supplied when creating the criteria lines
//     * @param boolean $removeUnbound Whether or not to remove expressions that have not been supplied with a value
//     * @param bool $exceptionOnInvalidNull If value is null, and operator does not require null, whether to throw an exception
//     * (if false, it will be converted to an empty string so as not to break the SQL).
//     * @return array
//     */
//    public function build(
//        array $params = [],
//        bool $removeUnbound = true,
//        bool $exceptionOnInvalidNull = true
//    ): array {
//        foreach ($this->expressions as $index => $expression) {
//            $expression->applyValues($params, false, $removeUnbound, $exceptionOnInvalidNull);
//            if ($removeUnbound && $expression->hasUnboundParameters()) {
//                $this->expressions[$index] = null;
//            }
//        }
//        $this->expressions = array_values(array_filter($this->expressions));
//
//        foreach ($this->joins as $join) {
//            /** @var JoinExpression $join */
//            $join->extraCriteria = $join->extraCriteriaBuilder
//                ? $join->extraCriteriaBuilder->build($params, $removeUnbound, $exceptionOnInvalidNull)
//                : [];
//        }
//
//        return array_merge($this->expressions, $this->joins);
//    }

//    /**
//     * Build an order by array with the correct entity/property names based on a request that uses tokens.
//     * For example, if the request comes in as:
//     * {
//     *     "orderBy": {
//     *         "lastName": "DESC",
//     *         "id": "ASC"
//     *     }
//     * }
//     * ...you could pass in ['lastName'=>'DESC', 'id'=>'ASC'] as the $orderBy parameter, and
//     * ['lastName'=>'customer.surname', 'id'=>'policy.id'] as the $tokenTranslations parameter, and
//     * this method will return an orderBy array that you can use with Objectiphy:
//     * ['customer.surname'=>'DESC', 'policy.id'=>'ASC'].
//     * This is just a convenience method to save you having to build the array yourself. If you are re-using a criteria
//     * builder that you used to build a criteria array, and the criteria included translated tokens, those translations
//     * will be used in addition to any that you supply in $tokenTranslations ($tokenTranslations can be omitted if
//     * that already covers all of your translation requirements).
//     * @param array $orderBy The order by request (with tokens that might not match property names)
//     * @param array $tokenTranslations Translations from tokens to property names
//     * @param boolean $preserveUntranslated If some of the fields passed in do not have a translation, this option
//     * specifies whether or not to preserve them (ie. return them as they are, without any translation). If false,
//     * any such values will be omitted from the return value.
//     * @return array
//     */
//    public function buildOrderBy(
//        array $orderBy,
//        array $tokenTranslations = [],
//        bool $preserveUntranslated = true
//    ): array {
//        $this->translatedFieldNames = array_merge($this->translatedFieldNames, $tokenTranslations);
//
//        return $this->getTranslatedFieldNames($orderBy, true, $preserveUntranslated);
//    }

    /**
     * Clear all expressions that have been added.
     */
    public function reset(): QueryBuilder
    {
        $this->select = [];
        $this->from = '';
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->groupBy = [];
        $this->having = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;

        return $this;
    }

//    /**
//     * @return array
//     */
//    public function getAndExpressions(): array
//    {
//        return $this->joinWith == 'AND' ? $this->expressions : [];
//    }
//
//    /**
//     * @return array
//     */
//    public function getOrExpressions(): array
//    {
//        return $this->joinWith == 'OR' ? $this->expressions : [];
//    }



    /**
     * @param string $type 'LEFT' or 'INNER'
     */
    protected function addJoin($targetEntityClassName, $alias, $type = 'LEFT')
    {
        //First line of criteria must link a property on the alias to a property of a known entity
        /** @var CriteriaExpression $firstExpression */
        $firstExpression = array_shift($on->expressions);
        $firstProperty = $firstExpression ? $firstExpression->property : '';
        $firstValue = $firstExpression ? $firstExpression->value : '';

        if (strpos($firstProperty, $alias . '.') !== 0) {
            $errorMessage = sprintf('First criteria expression specified for the $on argument when adding a join must refer to the alias. The alias you specified was \'%1$s\', so the propertyName on the first criteria expression for the $on argument should start with \'%1$s.\'', $alias);
            throw new QueryException($errorMessage);
        }
        if (substr($firstValue, 0, 1) !== '`' || substr($firstValue, strlen($firstValue) -1) !== '`') {
            $errorMessage = 'First criteria expression specified for the $on argument when adding a join must refer to a property in the object hierarchy (ie. the value should be surrounded by backticks).';
            throw new QueryException($errorMessage);
        }

        $this->joins[] = new JoinExpression(
            str_replace('`', '', $firstValue),
            $firstExpression->operator,
            $targetEntityClassName,
            substr($firstProperty, strlen($alias) + 1),
            $alias,
            $type
        );

        return $this;
    }

//    /**
//     * If the build method has been called, we can translate tokens into property names for all or specified fields.
//     * This can be useful for quickly creating an orderBy array without having to translate all the field names again.
//     * @param array|null $fields
//     * @param bool $useKeys If used to create an orderBy array, and you have the field names as keys, and direction
//     * as values, setting this to true translates the field names in the keys and preserves the values (false will
//     * assume an indexed array and will translate field names in the values).
//     * @param bool $preserveUntranslated If some of the fields passed in do not have a translation, this option
//     * specifies whether or not to preserve them (ie. return them as they are, without any translation). If false,
//     * any such values will be omitted from the return value.
//     * @return array
//     */
//    protected function getTranslatedFieldNames(
//        array $fields = null,
//        bool $useKeys = true,
//        bool $preserveUntranslated = true
//    ): array {
//        $translatedFieldNames = [];
//        foreach ($this->translatedFieldNames as $key=>$translatedFieldName) {
//            if (($useKeys && !empty($fields[$key])) || (!$useKeys && in_array($key, $fields))) {
//                $fieldIndex = $fields ? array_search($key, $useKeys ? array_keys($fields) : $fields) : false;
//                if ($fields === null || $fieldIndex !== false) {
//                    $newIndex = $useKeys ? $translatedFieldName : count($translatedFieldNames);
//                    $newValue = $useKeys ? $fields[$key] : $translatedFieldName;
//                    $translatedFieldNames[$newIndex] = $newValue;
//                }
//            }
//        }
//
//        //Any fields passed in that do not have a translation, just use their original key/value
//        if ($preserveUntranslated && $fields) {
//            foreach ($fields as $key=>$value) {
//                if (!isset($this->translatedFieldNames[$useKeys ? $key : $value])) {
//                    $translatedFieldNames[$useKeys ? $key : count($translatedFieldNames)] = $value;
//                }
//            }
//        }
//
//        return $translatedFieldNames;
//    }
}
