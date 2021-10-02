<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Identifies an object as a query (SelectQuery, UpdateQuery, InsertQuery, DeleteQuery)
 */
interface QueryInterface extends PropertyPathConsumerInterface
{
    public function __toString(): string;
    
    public function setFields(FieldExpression ...$fields): void;

    public function getFields(): array;

    public function setClassName(string $className): void;

    public function getClassName(): string;

    public function setJoins(JoinPartInterface ...$joins): void;

    public function getJoins(): array;

    public function setWhere(CriteriaPartInterface ...$criteria): void;

    public function getWhere(): array;

    public function setHaving(CriteriaExpression ...$criteria): void;

    public function getHaving(): array;
    
    public function &getParams(): array;

    public function setParams(array $params): void;

    public function getParam(string $paramName);

    /**
     * @param $paramValue
     * @param $paramName
     * @return string Name of the parameter (either the value supplied in $paramName, or a generated name if that is blank)
     */
    public function addParam($paramValue, ?string $paramName = null): string;

    public function getPropertyPaths(bool $includingAggregateFunctions = true): array;

    public function getClassesUsed(): array;

    /**
     * Extract target class name from a join.
     * @param string $alias
     * @return string
     */
    public function getClassForAlias(string $alias): string;

    /**
     * Ensure query is complete, filling in any missing bits as necessary
     * @param MappingCollection $mappingCollection
     * @param SqlStringReplacer $stringReplacer
     * @param string|null $className
     */
    public function finalise(
        MappingCollection $mappingCollection,
        SqlStringReplacer $stringReplacer,
        ?string $className = null
    ): void;
}
