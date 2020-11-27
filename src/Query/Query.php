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

    public function setFields(FieldExpression ...$fields): void
    {
        $this->fields = $fields;
    }
    
    public function getFields(): array
    {
        return $this->fields;
    }

    public function setClassName(string $className): void
    {
        $this->className = $className;
    }
    
    public function getClassName(): string
    {
        return $this->className ?? '';
    }
    
    public function setJoins(JoinExpression ...$joins): void
    {
        $this->joins = $joins;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function setWhere(CriteriaPartInterface ...$criteria): void
    {
        $this->where = $criteria;
    }

    public function getWhere(): array
    {
        return $this->where;
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
     */
    public function finalise(MappingCollection $mappingCollection, ?string $className = null)
    {
        if (!$this->isFinalised) {
            $this->mappingCollection = $mappingCollection;
            $relationships = $mappingCollection->getRelationships();
            foreach ($relationships as $propertyMapping) {
                $this->populateRelationshipJoin($mappingCollection, $propertyMapping);
            }
            $className = $this->getClassName() ?: ($className ?? $mappingCollection->getEntityClassName());
            $this->setClassName($className);
            $this->isFinalised = true; //Overriding superclass could change this back if it has its own finalising to do.
        }
    }

    protected function populateRelationshipJoin(MappingCollection $mappingCollection, PropertyMapping $propertyMapping)
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
