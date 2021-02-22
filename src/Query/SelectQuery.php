<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
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
     * Ensure we have all the primary keys for everything that's selected (so late binding will work if needed).
     * This must be called before adding query mappings and therefore separately from finalise.
     * @param MappingCollection $mappingCollection
     */
    public function selectPrimaryKeys(MappingCollection $mappingCollection)
    {
        $selects = [];
        foreach ($this->getSelect() as $fieldExpression) {
            if ($fieldExpression->isPropertyPath() && strpos($fieldExpression->getExpression(), '.') !== false) {
                $propertyMapping = $mappingCollection->getPropertyMapping($fieldExpression->getExpression());
                if ($propertyMapping) {
                    $parentPropertyMapping = $mappingCollection->getPropertyMapping($propertyMapping->getParentPath());
                    if ($parentPropertyMapping && $parentPropertyMapping->column->name) {
                        $selects[] = new FieldExpression($parentPropertyMapping->getPropertyPath());
                    } else {
                        $pks = $mappingCollection->getPrimaryKeyProperties($propertyMapping->className);
                        foreach ($pks as $pk) {
                            $selects[] = new FieldExpression($propertyMapping->getParentPath() . '.' . $pk);
                        }
                    }
                }
            }
        }
        if ($selects) {
            $this->setSelect(...array_merge($this->getSelect(), $selects));
        }
    }

    /**
     * Ensure query is complete, filling in any missing bits as necessary
     * @param MappingCollection $mappingCollection
     * @param string|null $className
     * @throws QueryException
     * @throws MappingException
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
}
