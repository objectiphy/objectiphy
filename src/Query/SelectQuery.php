<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;

class SelectQuery implements QueryInterface
{
    /**
     * @var FieldExpression[]
     */
    private array $select = [];

    /**
     * @var string Main (parent) entity class name
     */
    private string $from;
    
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

    private ?int $limit = null;
    private ?int $offset = null;
    private MappingCollection $mappingCollection;
    private bool $isFinalised = false;

    public function setSelect(FieldExpression ...$fields)
    {
        $this->select = $fields;
    }
    
    public function getSelect(): array
    {
        return $this->select;
    }

    public function setFrom(string $className)
    {
        $this->from = $className;
    }
    
    public function getFrom(): string 
    {
        return $this->from;
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
    
    public function setLimit(int $limit)
    {
        $this->limit = $imit;
    }
    
    public function getLimit(): ?int
    {
        return $this->limit;
    }
    
    public function setOffset(int $offset)
    {
        $this->offset = $offset;
    }
    
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getPropertyPaths(): array
    {
        $paths = [];
        foreach ($this->select ?? [] as $select) {
            $paths = array_merge($paths, $select->getPropertyPaths());
        }
        foreach ($this->where ?? [] as $where) {
            $paths = array_merge($paths, $where->getPropertyPaths());
        }
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
            $this->mappingCollection = $mappingCollection;
            if (!$this->select) {
                $fetchables = $mappingCollection->getFetchableProperties();
                foreach ($fetchables as $fetchable) {
                    if ($fetchable->getFullColumnName()) {
                        $this->select[] = new FieldExpression($fetchable->getPropertyPath());
                    }
                }
            }
            $relationships = $mappingCollection->getRelationships();
            foreach ($relationships as $propertyMapping) {
                $this->populateRelationshipJoin($mappingCollection, $propertyMapping);
            }
            $this->from = $this->from ?? $className ?? $mappingCollection->getEntityClassName();
            if (!$this->orderBy) {
                //See if we can order by primary key of main entity
                $pkProperties = $mappingCollection->getPrimaryKeyProperties();
                foreach ($pkProperties as $pkProperty) {
                    $this->orderBy[] = new FieldExpression('`' . $pkProperty . '` ASC', false);
                }
            }
            $this->isFinalised = true;
        }
    }

    public function __toString(): string
    {
        if (!$this->select || !$this->from) {
            throw new QueryException('Please finalise the query before use (ie. call the finalise method).');
        }
        $useParams = $params !== null;
        $queryString = 'SELECT ' . (implode(', ', $this->getSelect())) ?: '*';
        $queryString .= ' FROM ' . $this->from;
        $queryString .= ' ' . implode(' ', $this->getJoins());
        $queryString .= ' WHERE 1 ';
        if ($this->where) {
            foreach ($this->getWhere() as $criteriaExpression) {
                $queryString .= ' AND ' . ($useParams
                        ? $criteriaExpression->toString($params)
                        : (string) $criteriaExpression);
            }
        }
        if ($this->groupBy) {
            $queryString .= ' GROUP BY ' . implode(', ', $this->getGroupBy());
        }
        if ($this->having) {
            $queryString .= ' HAVING 1 ';
            foreach ($this->getHaving() as $criteriaExpression) {
                $queryString .= ' AND ' . ($useParams
                        ? $criteriaExpression->toString($params)
                        : (string) $criteriaExpression);
            }
        }
        if ($this->orderBy) {
            $queryString .= ' ORDER BY ' . implode(', ', $this->getOrderBy());
        }
        
        return $queryString;
    }

    private function populateRelationshipJoin(MappingCollection $mappingCollection, PropertyMapping $propertyMapping)
    {
        if ($propertyMapping->isLateBound(true)) {
            return;
        }

        $targetProperty = $propertyMapping->relationship->getTargetProperty();
        if (!$targetProperty) { //Just joining to single primary key value
            $pkProperties = $mappingCollection->getPrimaryKeyProperties($propertyMapping->getChildClassName());
            $targetProperty = reset($pkProperties);
        }

        $join = new JoinExpression(
            $propertyMapping->getPropertyPath(),
            '=',
            $propertyMapping->getChildClassName(),
            $targetProperty,
            'obj_alias_' . str_replace('.', '_', $propertyMapping->getPropertyPath())
        );
        $join->propertyMapping = $propertyMapping;
        
        $this->joins[] = $join;
    }
}
