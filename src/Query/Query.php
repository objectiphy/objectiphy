<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
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
    protected SqlStringReplacer $stringReplacer;

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
     * @param SqlStringReplacer $stringReplacer
     * @param string|null $className
     * @throws MappingException
     * @throws QueryException
     */
    public function finalise(
        MappingCollection $mappingCollection,
        SqlStringReplacer $stringReplacer,
        ?string $className = null
    ): void {
        $this->stringReplacer = $stringReplacer;
        if (!$this->isFinalised) {
            $className = $this->getClassName() ?: ($className ?? $mappingCollection->getEntityClassName());
            $this->setClassName($className);
            $this->mappingCollection = $mappingCollection;
            $relationships = $this->getRelationshipsUsed();
            foreach ($relationships as $propertyMapping) {
                $this->populateRelationshipJoin($propertyMapping);
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
     * @param PropertyMapping $propertyMapping
     * @throws QueryException
     */
    protected function populateRelationshipJoin(
        PropertyMapping $propertyMapping
    ): void {
        if ($propertyMapping->isLateBound(true)) {
            return;
        }

        $ons = [];
        if ($propertyMapping->relationship->isScalarJoin()) {
            $join = $this->populateScalarJoin($propertyMapping, $ons);
        } elseif ($propertyMapping->relationship->targetJoinColumn) {
            $join = $this->populateMappedJoin($propertyMapping, $ons);
        } else {
            $join = $this->populatePkPropertyJoin($propertyMapping, $ons);
        }

        if (!empty($join) && count($ons) > 0) {
            $join->propertyMapping = $propertyMapping;
            $this->joins[] = $join;
            foreach ($ons as $on) {
                $this->joins[] = $on;
            }
        }
    }

    /**
     * @param PropertyMapping $propertyMapping
     * @param $ons
     * @return JoinExpression|null
     * @throws QueryException
     */
    private function populateScalarJoin(PropertyMapping $propertyMapping, array &$ons): ?JoinExpression
    {
        $join = null;
        $target = $propertyMapping->relationship->targetJoinColumn;
        if ($target) {
            $join = new JoinExpression(
                $this->stringReplacer->delimit($propertyMapping->relationship->joinTable),
                'obj_alias_' . str_replace('.', '_', $propertyMapping->getPropertyPath())
            );
            $ons[] = new CriteriaExpression(
                new FieldExpression($propertyMapping->getPropertyPath()),
                $propertyMapping->getAlias(),
                QB::EQ,
                $target
            );
        }

        return count($ons ?? []) > 0 ? $join : null;
    }

    /**
     * @param PropertyMapping $propertyMapping
     * @param array $ons
     * @return JoinExpression|null
     * @throws QueryException
     */
    private function populateMappedJoin(PropertyMapping $propertyMapping, array &$ons): ?JoinExpression
    {
        $propertyDelimiter = $this->stringReplacer->getDelimiter('property');
        $alias = 'obj_alias_' . str_replace('.', '_', $propertyMapping->getPropertyPath());
        $join = new JoinExpression(
            $this->stringReplacer->delimit($propertyMapping->relationship->joinTable),
            $alias
        );
        $sourceColumns = explode(',', $propertyMapping->relationship->sourceJoinColumn);
        $targetColumns = explode(',', $propertyMapping->relationship->targetJoinColumn);
        foreach ($targetColumns as $index => $targetColumn) {
            $source = $sourceColumns[$index]
                ? $this->stringReplacer->delimit(trim($sourceColumns[$index]))
                : $this->stringReplacer->delimit($propertyMapping->getPropertyPath(), $propertyDelimiter);
            if ($propertyMapping->relationship->isScalarJoin()) {
                $target = $this->stringReplacer->delimit(trim($targetColumn));
            } else {
                $target = $this->stringReplacer->delimit($alias . '.' . trim($targetColumn));
            }
            $ons[] = new CriteriaExpression(
                new FieldExpression($this->stringReplacer->delimit($propertyMapping->getTableAlias(false, true, true) . '.' . $source)),
                $propertyMapping->getAlias(),
                QB::EQ,
                $target
            );
        }

        return count($ons ?? []) > 0 ? $join : null;
    }

    /**
     * @param PropertyMapping $propertyMapping
     * @param array $ons
     * @throws QueryException
     */
    private function populatePkPropertyJoin(PropertyMapping $propertyMapping, array &$ons): ?JoinExpression
    {
        $propertyDelimiter = $this->stringReplacer->getDelimiter('property');
        $targetProperty = $propertyMapping->relationship->getTargetProperty();
        if (!$targetProperty) { //Just joining to single primary key value
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties($propertyMapping->getChildClassName());
            $targetProperty = reset($pkProperties);
        }
        $join = new JoinExpression(
            $propertyMapping->getChildClassName(),
            'obj_alias_' . str_replace('.', '_', $propertyMapping->getPropertyPath())
        );
        $prefix = $propertyMapping->getParentPath() ? $propertyMapping->getParentPath() . '.' : '';
        $target = $targetProperty ? $propertyDelimiter . $prefix . $targetProperty . $propertyDelimiter : '';
        if ($target) {
            $ons[] = new CriteriaExpression(
                new FieldExpression($propertyMapping->getPropertyPath()),
                $propertyMapping->getAlias(),
                QB::EQ,
                $target
            );
        }

        return count($ons ?? []) > 0 ? $join : null;
    }
}
