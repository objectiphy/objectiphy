<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Query to select one or more entities from the database.
 */
class SelectQuery extends Query implements SelectQueryInterface
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

    public function setGroupBy(FieldExpression ...$fields): void
    {
        $this->groupBy = $fields;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function setHaving(CriteriaPartInterface ...$criteria): void
    {
        $this->having = $criteria;
    }

    public function getHaving(): array
    {
        return $this->having;
    }

    public function setOrderBy(FieldExpression ...$fields): void
    {
        $this->orderBy = $fields;
    }

    public function getOrderBy(): array
    {
        return $this->orderBy;
    }
    
    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }
    
    public function getLimit(): ?int
    {
        return $this->limit;
    }
    
    public function setOffset(?int $offset): void
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
    public function finalise(MappingCollection $mappingCollection, ?string $className = null): void
    {
        if (!$this->isFinalised) {
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
////This is probably a waste of resources
//            if (!$this->orderBy) {
//                //See if we can order by primary key of main entity
//                $pkProperties = $mappingCollection->getPrimaryKeyProperties();
//                foreach ($pkProperties as $pkProperty) {
//                    $this->orderBy[] = new FieldExpression('%' . $pkProperty . '% ASC', false);
//                }
//            }
            parent::finalise($mappingCollection, $className);
        }
    }

    public function __toString(): string
    {
        if (!$this->getSelect() || !$this->getFrom()) {
            throw new QueryException('Please finalise the query before use (ie. call the finalise method).');
        }

        $queryString = 'SELECT ' . (implode(', ', $this->getSelect())) ?: '*';
        $queryString .= ' FROM ' . $this->getFrom();
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

    /**
     * Override if required to only return the relationships actually needed for the query
     * @return PropertyMapping[]
     */
    protected function getRelationshipsUsed(): array
    {
        $relationshipsUsed = [];
        $propertyPathsUsed = $this->getPropertyPaths();
        $relationships = $this->mappingCollection->getRelationships();
        foreach ($relationships as $key => $relationship) {
            foreach ($propertyPathsUsed as $propertyPath) {
                if (in_array($relationship->propertyName, explode('.', $propertyPath))) {
                    $relationshipsUsed[$key] = $relationship;
                    break;
                }
            }
        }

        return $relationshipsUsed;
    }
}
