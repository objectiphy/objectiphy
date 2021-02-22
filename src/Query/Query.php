<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Base class for SelectQuery, InsertQuery, UpdateQuery, and DeleteQuery
 */
abstract class Query implements QueryInterface
{
    /**
     * Fields to operate on (select, update, or insert)
     * @var FieldExpression[]
     */
    protected array $fields = [];

    /**
     * @var string Main (parent) entity class name
     */
    protected string $className;
    
    /**
     * @var JoinExpression[]
     */
    protected array $joins = [];

    /**
     * @var CriteriaExpression[]
     */
    protected array $where = [];

    protected MappingCollection $mappingCollection;
    protected bool $isFinalised = false;
    protected array $params = [];

    public function setFields(FieldExpression ...$fields): void
    {
        $this->fields = $fields;
    }
    
    public function getFields(): array
    {
        return $this->fields ?? [];
    }

    public function setClassName(string $className): void
    {
        $this->className = $className;
    }
    
    public function getClassName(): string
    {
        return $this->className ?? '';
    }
    
    public function setJoins(JoinPartInterface ...$joins): void
    {
        $this->joins = $joins;
    }

    public function getJoins(): array
    {
        return $this->joins ?? [];
    }

    public function getJoinAliases(): array
    {
        $joins = $this->getJoins();
        foreach ($joins as $joinExpression) {
            if ($joinExpression instanceof JoinExpression
                && $joinExpression->joinAlias && $joinExpression->targetEntityClassName) {
                $aliases[$joinExpression->joinAlias] = $joinExpression->targetEntityClassName;
            }
        }
    }

    public function setWhere(CriteriaPartInterface ...$criteria): void
    {
        $this->where = $criteria;
    }

    public function getWhere(): array
    {
        return $this->where ?? [];
    }

    public function getPropertyPaths(): array
    {
        $paths = [];
        foreach ($this->fields ?? [] as $field) {
            $paths = array_merge($paths, $field->getPropertyPaths());
        }
        foreach ($this->joins ?? [] as $join) {
            if ($join instanceof PropertyPathConsumerInterface) {
                $paths = array_merge($paths, $join->getPropertyPaths());
            }
        }
        foreach ($this->where ?? [] as $where) {
            if ($where instanceof PropertyPathConsumerInterface) {
                $paths = array_merge($paths, $where->getPropertyPaths());
            }
        }

        return array_unique($paths);
    }

    /**
     * Ensure query is complete, filling in any missing bits as necessary
     * @param MappingCollection $mappingCollection
     * @param string|null $className
     * @throws MappingException
     * @throws QueryException
     */
    public function finalise(MappingCollection $mappingCollection, ?string $className = null): void
    {
        if (!$this->isFinalised) {
            $className = $this->getClassName() ?: ($className ?? $mappingCollection->getEntityClassName());
            $this->setClassName($className);
            $this->mappingCollection = $mappingCollection;
            $relationships = $this->getRelationshipsUsed();
            foreach ($relationships as $propertyMapping) {
                $this->populateRelationshipJoin($mappingCollection, $propertyMapping);
            }
            $this->isFinalised = true; //Overriding subclass could change this back if it has its own finalising to do.
        }
    }

    public function getClassesUsed(): array
    {
        $classesUsed = !empty($this->className) ? [$this->className] : [];
        foreach ($this->joins as $joinExpression) {
            if ($joinExpression instanceof JoinExpression) {
                $classesUsed[] = $joinExpression->targetEntityClassName;
            }
        }

        return $classesUsed;
    }

    /**
     * Extract target class name from a join.
     * @param string $alias
     * @return string
     */
    public function getClassForAlias(string $alias): string
    {
        foreach ($this->joins as $joinExpression) {
            if ($joinExpression instanceof JoinExpression) {
                if ($joinExpression->joinAlias == $alias) {
                    return $joinExpression->targetEntityClassName;
                }
            }
        }

        return '';
    }

    public function &getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParam(string $paramName)
    {
        return $this->params[$paramName] ?? null;
    }

    public function addParam($paramValue, ?string $paramName = null): string
    {
        $existingParam = array_search($paramValue, $this->params);
        if ($existingParam !== false) {
            return $existingParam;
        }

        if (!$paramName) {
            $paramName = 'param_' . strval(count($this->params ?? []) + 1);
        }
        $this->params[$paramName] = $paramValue;

        return $paramName;
    }

    /**
     * Override if required to only return the relationships actually needed for the query
     * @return PropertyMapping[]
     * @throws MappingException
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

    /**
     * Put together the parts of a join - relationship info and criteria.
     * @param MappingCollection $mappingCollection
     * @param PropertyMapping $propertyMapping
     * @throws QueryException
     */
    protected function populateRelationshipJoin(
        MappingCollection $mappingCollection,
        PropertyMapping $propertyMapping
    ): void {
        if ($propertyMapping->isLateBound(true)) {
            return;
        }

        if ($propertyMapping->relationship->isScalarJoin()) {
            // There is no property to join to - just use the columns.
            $target = $propertyMapping->relationship->targetJoinColumn;
            $join = new JoinExpression(
                '`' . str_replace('.', '`.`', $propertyMapping->relationship->joinTable) . '`',
                'obj_alias_' . str_replace('.', '_', $propertyMapping->getPropertyPath())
            );
        } else {
            $targetProperty = $propertyMapping->relationship->getTargetProperty();
            if (!$targetProperty) { //Just joining to single primary key value
                $pkProperties = $mappingCollection->getPrimaryKeyProperties($propertyMapping->getChildClassName());
                $targetProperty = reset($pkProperties);
            }
            $join = new JoinExpression(
                $propertyMapping->getChildClassName(),
                'obj_alias_' . str_replace('.', '_', $propertyMapping->getPropertyPath())
            );
            $target = "%$targetProperty%";
        }


        $join->propertyMapping = $propertyMapping;
        $on = new CriteriaExpression(
            new FieldExpression($propertyMapping->getPropertyPath(), true),
            $propertyMapping->getAlias(),
            QB::EQ,
            $target
        );

        $this->joins[] = $join;
        $this->joins[] = $on;
    }
}
