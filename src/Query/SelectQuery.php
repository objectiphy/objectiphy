<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;

class SelectQuery extends Query implements QueryInterface
{
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

    private ?int $limit = null;
    private ?int $offset = null;

    public function setSelect(FieldExpression ...$fields): void
    {
        $this->setFields(...$fields);
    }

    public function getSelect(): array
    {
        return $this->getFields();
    }

    public function setFrom(string $className): void
    {
        $this->setClassName($className);
    }

    public function getFrom(): string
    {
        return $this->getClassName();
    }

    public function setGroupBy(FieldExpression ...$fields)
    {
        $this->groupBy = $fields;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function setHaving(CriteriaPartInterface ...$criteria)
    {
        $this->having = $criteria;
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
    
    public function setLimit(?int $limit)
    {
        $this->limit = $limit;
    }
    
    public function getLimit(): ?int
    {
        return $this->limit;
    }
    
    public function setOffset(?int $offset)
    {
        $this->offset = $offset;
    }
    
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getPropertyPaths(): array
    {
        $paths = parent::getPropertyPaths();
        foreach ($this->groupBy ?? []  as $groupBy) {
            $paths = array_merge($paths, $groupBy->getPropertyPaths());
        }
        foreach ($this->orderBy ?? [] as $orderBy) {
            $paths = array_merge($paths, $orderBy->getPropertyPaths());
        }

        return array_unique($paths);
    }

    /**
     * Ensure query is complete, filling in any missing bits as necessary
     * @param MappingCollection $mappingCollection
     * @param string|null $className
     */
    public function finalise(MappingCollection $mappingCollection, ?string $className = null)
    {
        if (!$this->isFinalised) {
            parent::finalise($mappingCollection, $className);
            $this->isFinalised = false; //Hold your horses, we're not done yet.
            if (!$this->getSelect()) {
                $fetchables = $mappingCollection->getFetchableProperties();
                $selects = [];
                foreach ($fetchables as $fetchable) {
                    if ($fetchable->getFullColumnName()) {
                        $selects[] = new FieldExpression($fetchable->getPropertyPath());
                    }
                }
                $this->setSelect(...$selects);
            }
            if (!$this->orderBy) {
                //See if we can order by primary key of main entity
                $pkProperties = $mappingCollection->getPrimaryKeyProperties();
                foreach ($pkProperties as $pkProperty) {
                    $this->orderBy[] = new FieldExpression('`' . $pkProperty . '` ASC', false);
                }
            }
            $this->isFinalised = true; //OK, now we're done.
        }
    }

    public function __toString(): string
    {
        if (!$this->select || !$this->from) {
            throw new QueryException('Please finalise the query before use (ie. call the finalise method).');
        }

        $queryString = 'SELECT ' . (implode(', ', $this->getSelect())) ?: '*';
        $queryString .= ' FROM ' . $this->from;
        $queryString .= ' ' . implode(' ', $this->getJoins());
        $queryString .= ' WHERE 1 ';
        if ($this->where) {
            foreach ($this->getWhere() as $criteriaExpression) {
                $queryString .= ' AND ' . (string) $criteriaExpression;
            }
        }
        if ($this->groupBy) {
            $queryString .= ' GROUP BY ' . implode(', ', $this->getGroupBy());
        }
        if ($this->having) {
            $queryString .= ' HAVING 1 ';
            foreach ($this->getHaving() as $criteriaExpression) {
                $queryString .= ' AND ' . (string) $criteriaExpression;
            }
        }
        if ($this->orderBy) {
            $queryString .= ' ORDER BY ' . implode(', ', $this->getOrderBy());
        }
        
        return $queryString;
    }
}
