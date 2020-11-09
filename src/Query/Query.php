<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

class Query
{
    /**
     * @var FieldExpression[]
     */
    private array $select = [];

    /**
     * @var JoinExpression[]
     */
    private array $joins = [];

    /**
     * @var CriteriaExpression[]
     */
    private array $where = [];

    /**
     * @var FieldExpression[]
     */
    private array $groupBy = [];

    /**
     * @var CriteriaExpression[]
     */
    private array $having = [];

    /**
     * @var FieldExpression[]
     */
    private array $orderBy = [];

    private Pagination $pagination;

    public function setSelect(FieldExpression ...$fields)
    {
        $this->select = $fields;
    }
    
    public function getSelect(): array
    {
        return $this->select;
    }

    public function setJoins(JoinExpression ...$joins)
    {
        $this->joins = $joins;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function setWhere(CriteriaExpression ...$criteria)
    {
        $this->where = $criteria;
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    public function setGroupBy(FieldExpression ...$fields)
    {
        $this->groupBy = $fields;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function setHaving(CriteriaExpression ...$critiera)
    {
        $this->having = $critiera;
    }

    public function getHaving(): array
    {
        return $this->having;
    }

    public function setOrderBy(FieldExpression ...$fields)
    {
        $this->orderBy = $fields;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getPropertyPaths(): array
    {
        $paths = [];
        foreach ($this->select ?? [] as $select) {
            $paths = array_merge($paths, $select->getPropertyPathsUsed());
        }
        foreach ($this->where ?? [] as $where) {
            $paths = array_merge($paths, $where->getPropertyPathsUsed());
        }
        foreach ($this->groupBy ?? []  as $groupBy) {
            $paths = array_merge($paths, $groupBy->getPropertyPathsUsed());
        }
        foreach ($this->orderBy ?? [] as $orderBy) {
            $paths = array_merge($paths, $orderBy->getPropertyPathsUsed());
        }

        return $paths;
    }
}
