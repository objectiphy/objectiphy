<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\QueryBuilder;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for where clause on MySQL
 */
class WhereProviderMySql
{
    private SqlStringReplacer $stringReplacer;

    public function __construct(SqlStringReplacer $stringReplacer)
    {
        $this->stringReplacer = $stringReplacer;
    }

    /**
     * The WHERE part of the SQL query.
     * @param QueryInterface $query
     * @param MappingCollection $mappingCollection
     * @return string
     * @throws ObjectiphyException
     */
    public function getWhere(QueryInterface $query, MappingCollection $mappingCollection): string
    {
        $sql = "\n/* where */\nWHERE 1";
        $removeJoiner = false;
        foreach ($query->getWhere() as $index => $criteriaExpression) {
            $sql .= $this->buildCriteriaSql($query, $index, $criteriaExpression, $mappingCollection, $removeJoiner);
        }
        $sql = rtrim($this->stringReplacer->replaceNames($sql));

        return $sql;
    }

    public function getHaving(QueryInterface $query, MappingCollection $mappingCollection): string
    {
        $sql = "\n/* having */\nHAVING 1";
        $where = $query->getWhere();
        $criteriaExpressions = array_merge($where, $query->getHaving());
        $nestingLevelSql = '';
        $nestingLevelHasSql = false;
        $removeJoiner = false;
        foreach ($criteriaExpressions as $index => $criteriaExpression) {
            $isGroupDelimiter = false;
            if ($criteriaExpression instanceof CriteriaGroup) {
                if ($criteriaExpression->type == CriteriaGroup::GROUP_TYPE_END) {
                    if ($nestingLevelHasSql) {
                        $sql .= $nestingLevelSql;
                        $nestingLevelHasSql = '';
                        $nestingLevelHasSql = false;
                        $removeJoiner = false;
                    }
                }
                $isGroupDelimiter = true;
            }
            $isWhere = $index < count($where);
            $thisSql = $this->buildCriteriaSql($query, $index, $criteriaExpression, $mappingCollection, $removeJoiner, $isWhere);
            $nestingLevelHasSql = $nestingLevelHasSql ?: strlen(trim($thisSql)) > 0 && !$isGroupDelimiter;
            $nestingLevelSql .= $thisSql;
        }
        if ($nestingLevelHasSql) {
            $sql .= $nestingLevelSql;
        }
        $sql = rtrim($this->stringReplacer->replaceNames($sql));

        return $sql == "\n/* having */\nHAVING 1" ? "" : $sql;
    }

    private function buildCriteriaSql(
        QueryInterface $query,
        int $index,
        CriteriaPartInterface $criteriaExpression,
        MappingCollection $mappingCollection,
        bool &$removeJoiner,
        bool $aggOnly = false
    ): string {
        $sql = '';
        if ($criteriaExpression instanceof CriteriaGroup) {
            $removeJoiner = $criteriaExpression->type != CriteriaGroup::GROUP_TYPE_END;
            if ($index == 0 && $criteriaExpression->type == CriteriaGroup::GROUP_TYPE_START_OR) {
                //If first item is an OR group, change it to AND, otherwise it ORs with 1 and matches every record!
                $sql .= "\n    AND " . substr((string) $criteriaExpression, 2);
            } else {
                $sql .= "\n    " . (string) $criteriaExpression;
            }
        } else {
            $propertyMapping = $mappingCollection->getPropertyMapping(strval($criteriaExpression->property));
            if (($propertyMapping->column->aggregateFunctionName ?? false) && !$aggOnly) {
                return ''; //This will be added to the HAVING section
            } elseif (!($propertyMapping->column->aggregateFunctionName ?? false) && $aggOnly) {
                return '';
            }
            if (!$removeJoiner) {
                $sql .= "\n    " . $criteriaExpression->joiner;
            }
            $sql .= " " . $this->addCriteriaSql($criteriaExpression, $query, $mappingCollection);
            $removeJoiner = false;
        }

        return $sql;
    }

    /**
     * @param CriteriaExpression $criteriaExpression
     * @param QueryInterface $query
     * @param MappingCollection $mappingCollection
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function addCriteriaSql(
        CriteriaExpression $criteriaExpression,
        QueryInterface $query,
        MappingCollection $mappingCollection
    ): string {
        // always parse property, if needed for a custom query the developer should ensure safety
        $originalParseDelimeterValue = $this->stringReplacer->parseDelimiters;
        $this->stringReplacer->parseDelimiters = true;
        $sql = $this->stringReplacer->getPersistenceValueForField(
            $query,
            $criteriaExpression->property->getExpression(),
            $mappingCollection
        );
        $this->stringReplacer->parseDelimiters = $originalParseDelimeterValue;
        if (!trim($sql)) {
            throw new QueryException(sprintf('Could not convert criteria expression \'%1$s\' into SQL - please check for syntax and typos.', $criteriaExpression->property->getExpression()));
        }
        $operator = $valuePrefix = $valueSuffix = '';
        $this->prepareExpression($criteriaExpression, $operator, $valuePrefix, $valueSuffix);
        $sql .= ' ' . $operator . ' ';
        if (is_array($criteriaExpression->value)) {
            $sql .= '(';
        }
        $values = is_array($criteriaExpression->value) ? $criteriaExpression->value : [$criteriaExpression->value];
        $stringValues = [];
        foreach ($values as $value) {
            $propertyMapping = $mappingCollection->getPropertyMapping($criteriaExpression->property->getPropertyPath());
            $dataType = $propertyMapping ? $propertyMapping->getDataType() : '';
            $format = $propertyMapping ? $propertyMapping->column->format : '';
            $stringValues[] = $this->stringReplacer->getPersistenceValueForField($query, $value, $mappingCollection, $dataType, $format, $valuePrefix, $valueSuffix, $operator == 'LIKE');
        }
        $sql .= implode(',', $stringValues);
        if (is_array($criteriaExpression->value)) {
            $sql .= ')';
        }
        if ($criteriaExpression->operator == QueryBuilder::BETWEEN) {
            $propertyMapping = $mappingCollection->getPropertyMapping($criteriaExpression->property->getPropertyPath());
            $dataType = $propertyMapping ? $propertyMapping->getDataType() : '';
            $format = $propertyMapping ? $propertyMapping->column->format : '';
            $sql .= ' AND ' . $this->stringReplacer->getPersistenceValueForField($query, $criteriaExpression->value2, $mappingCollection, $dataType, $format, $valuePrefix, $valueSuffix);
        }

        return $sql;
    }

    private function prepareExpression(
        CriteriaExpression $criteriaExpression,
        string &$operator,
        string &$valuePrefix,
        string &$valueSuffix
    ): void {
        switch ($criteriaExpression->operator) {
            case QB::BEGINS_WITH:
                $operator = 'LIKE';
                $valueSuffix = '%';
                break;
            case QB::ENDS_WITH:
                $operator = 'LIKE';
                $valuePrefix = '%';
                break;
            case QB::CONTAINS:
                $operator = 'LIKE';
                $valuePrefix = $valueSuffix = '%';
                break;
            default:
                $operator = $criteriaExpression->operator;
                break;
        }
    }
}
