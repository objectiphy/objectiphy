<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\QueryInterface;
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
        $sql = "WHERE 1\n";
        $removeJoiner = false;
        foreach ($query->getWhere() as $index => $criteriaExpression) {
            if ($criteriaExpression instanceof CriteriaGroup) {
                $removeJoiner = $criteriaExpression->type != CriteriaGroup::GROUP_TYPE_END;
                if ($index == 0 && $criteriaExpression->type == CriteriaGroup::GROUP_TYPE_START_OR) {
                    //If first item is an OR group, change it to AND, otherwise it ORs with 1 and matches every record!
                    $sql .= "    AND " . substr((string) $criteriaExpression, 2) . "\n";
                } else {
                    $sql .= "    " . (string) $criteriaExpression . "\n";
                }
            } else {
                if (!$removeJoiner) {
                    $sql .= "    " . $criteriaExpression->joiner;
                }
                $sql .= " " . $this->addCriteriaSql($criteriaExpression, $query, $mappingCollection);
                $removeJoiner = false;
            }
        }
        $sql = trim($this->stringReplacer->replaceNames($sql));

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
        $sql = $this->stringReplacer->getPersistenceValueForField(
            $query,
            $criteriaExpression->property->getExpression(),
            $mappingCollection
        );
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
            $stringValues[] = $this->stringReplacer->getPersistenceValueForField($query, $value, $mappingCollection, $dataType, $format, $valuePrefix, $valueSuffix);
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
