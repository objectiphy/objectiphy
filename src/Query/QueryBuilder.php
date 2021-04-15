<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaBuilderInterface;
use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\InsertQueryInterface;
use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Helper class to build a query that can be passed to a repository.
 */
class QueryBuilder extends CriteriaBuilder implements CriteriaBuilderInterface
{
    /**
     * @var FieldExpression[]
     */
    private array $select = [];

    private string $from = '';
    private string $insert = '';
    private string $update = '';
    private string $delete = '';

    /**
     * @var JoinPartInterface[]
     */
    private array $joins = [];

    /**
     * @var AssignmentExpression[]
     */
    private array $assignments = [];

    /**
     * @var array Array of assignment arrays for inserting multiple records in one query
     */
    private array $additionalAssignments = [];

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
    private array $orderByDirections = []; //Separate from $orderBy so the thing to order by can be resolved to a column

    private ?int $limit = null;
    private ?int $offset = null;

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
            $fieldExpression = new FieldExpression($field);
            $this->select[] = $fieldExpression;
        }

        return $this;
    }

    public function selectExpressions(FieldExpression ...$fields): QueryBuilder
    {
        $this->select = $fields;
        return $this;
    }

    public function insert(string $className): QueryBuilder
    {
        $this->insert = $className;
        return $this;
    }

    public function update(string $className): QueryBuilder
    {
        $this->update = $className;
        return $this;
    }

    public function from(string $className): QueryBuilder
    {
        $this->from = $className;
        return $this;
    }
    
    public function delete(string $className): QueryBuilder
    {
        $this->delete = $className;
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

    public function on(string $propertyName, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection =& $this->joins;
        return $this->and($propertyName, $operator, $value);
    }

    /**
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return $this
     * @throws QueryException
     */
    public function onExpression(FieldExpression $expression, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection =& $this->joins;
        $this->andExpression($expression, $operator, $value);

        return $this;
    }

    /**
     * @param array $propertyValues
     * @return $this
     * @throws QueryException
     */
    public function set(array $propertyValues): QueryBuilder
    {
        $assignments = [];
        foreach ($propertyValues as $propertyPath => $value) {
            if (!is_string($propertyPath)) {
                throw new QueryException(
                    'Keys for $propertyValues array when calling the `set` method on the QueryBuilder must be strings.'
                );
            }
            $assignments[] = new AssignmentExpression($propertyPath, $value);
        }

        if ($this->assignments) {
            $this->additionalAssignments[] = $assignments;
        } else {
            $this->setExpressions(...$assignments);
        }

        return $this;
    }

    public function setExpressions(AssignmentExpression ...$assignments): QueryBuilder
    {
        $this->assignments = $assignments;
        return $this;
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andWhere, as they do the same thing)
     * @param string $propertyName
     * @param string $operator
     * @param $value
     * @return QueryBuilder
     * @throws QueryException
     */
    public function where(string $propertyName, string $operator, $value): QueryBuilder
    {
        if ($this->groupNestingLevel == 0) {
            $this->currentCriteriaCollection =& $this->where;
        }

        return $this->and($propertyName, $operator, $value);
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andWhereExpression, as they do the same thing)
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return QueryBuilder
     * @throws QueryException
     */
    public function whereExpression(FieldExpression $expression, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection =& $this->where;
        $this->andExpression($expression, $operator, $value);

        return $this;
    }

    public function groupBy(string ...$propertyNames): QueryBuilder
    {
        foreach ($propertyNames as $propertyName) {
            $this->groupBy[] = new FieldExpression($propertyName);
        }

        return $this;
    }

    /**
     * Specify first line of having criteria (this is actually just an alias for andHaving, as they do the same thing)
     * @param string $propertyName
     * @param string $operator
     * @param $value
     * @return QueryBuilder
     * @throws QueryException
     */
    public function having(string $propertyName, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection =& $this->having;
        return $this->and($propertyName, $operator, $value);
    }

    /**
     * Specify first line of criteria (this is actually just an alias for andHavingExpression, as they do the same thing)
     * @param FieldExpression $expression
     * @param string $operator
     * @param $value
     * @return QueryBuilder
     * @throws QueryException
     */
    public function havingExpression(FieldExpression $expression, string $operator, $value): QueryBuilder
    {
        $this->currentCriteriaCollection =& $this->having;
        $this->andExpression($expression, $operator, $value);

        return $this;
    }

    /**
     * @param array $propertyNames Either a plain indexed array of properties to sort ascending, or an associative 
     * array with the key being the property name and the value being either ASC or DESC.
     * @return $this
     * @throws QueryException
     */
    public function orderBy(array $propertyNames): QueryBuilder
    {
        foreach ($propertyNames as $key => $value) {
            if (is_string($key) && in_array($value, ['ASC', 'DESC', 'asc', 'desc'])) {
                $fieldExpression = new FieldExpression($key);
                $direction = strtoupper($value);
            } elseif (is_int($key) && is_string($value)) {
                $fieldExpression = new FieldExpression($value);
                $direction = 'ASC';
            } else {
                throw new QueryException(
                    'Invalid orderBy properties. Please use property name as the key and direction as the value, or a numeric key and property name as the value (which defaults to ASC for direction)'
                );
            }
            $this->orderBy[] = $fieldExpression;
            $this->orderByDirections[] = $direction;
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

    public function buildSelectQuery(array $params = [], bool $removeUnbound = true): SelectQueryInterface
    {
        $this->applyValues($this->where, $params, $removeUnbound);
        $this->applyValues($this->having, $params, $removeUnbound);
        $this->applyValues($this->joins, $params, $removeUnbound);

        //TODO: Check we have valid info? Eg. that we don't have a JOIN without an ON

        $query = new SelectQuery();
        $query->setSelect(...$this->select);
        $query->setFrom($this->from);
        $query->setJoins(...$this->joins);
        $query->setWhere(...$this->where);
        $query->setGroupBy(...$this->groupBy);
        $query->setHaving(...$this->having);
        $query->setOrderBy(...$this->orderBy);
        $query->setOrderByDirections(...$this->orderByDirections);
        $query->setLimit($this->limit);
        $query->setOffset($this->offset);

        return $query;
    }

    public function buildUpdateQuery(): UpdateQueryInterface
    {
        $query = new UpdateQuery();
        $query->setUpdate($this->update);
        $query->setJoins(...$this->joins);
        $query->setAssignments(...$this->assignments);
        $query->setWhere(...$this->where);

        return $query;
    }

    public function buildInsertQuery(): InsertQueryInterface
    {
        $query = new InsertQuery();
        $query->setInsert($this->insert);
        $query->setAssignments(...$this->assignments);
        foreach ($this->additionalAssignments as $additionalAssignments) {
            $query->setAssignments(...$additionalAssignments);
        }

        return $query;
    }

    public function buildDeleteQuery(array $params = [], bool $removeUnbound = true): DeleteQueryInterface
    {
        $this->applyValues($this->where, $params, $removeUnbound);
        $this->applyValues($this->joins, $params, $removeUnbound);

        $query = new DeleteQuery();
        $query->setDelete($this->delete);
        $query->setJoins(...$this->joins);
        $query->setWhere(...$this->where);

        return $query;
    }

    /**
     * Clear all expressions that have been added.
     */
    public function reset(): QueryBuilder
    {
        $this->select = [];
        $this->from = '';
        $this->joins = [];
        $this->where = [];
        $this->groupBy = [];
        $this->having = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;

        return $this;
    }

    /**
     * @param $targetEntityClassName
     * @param $alias
     * @param string $type 'LEFT' or 'INNER'
     * @return QueryBuilder
     */
    protected function addJoin($targetEntityClassName, $alias, $type = 'LEFT'): QueryBuilder
    {
        $this->joins[] = new JoinExpression($targetEntityClassName, $alias, $type);
        return $this;
    }
}