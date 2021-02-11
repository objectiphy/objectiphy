<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Orm\ObjectMapper;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
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
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
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
        $sql .= ' ' . $criteriaExpression->operator . ' ';
        if (is_array($criteriaExpression->value)) {
            $sql .= '(';
        }
        $values = is_array($criteriaExpression->value) ? $criteriaExpression->value : [$criteriaExpression->value];
        $stringValues = [];
        foreach ($values as $value) {
            $stringValues[] = $this->stringReplacer->getPersistenceValueForField($query, $value, $mappingCollection);
        }
        $sql .= implode(',', $stringValues);
        if (is_array($criteriaExpression->value)) {
            $sql .= ')';
        }
        if ($criteriaExpression->operator == QueryBuilder::BETWEEN) {
            $sql .= ' AND ' . $this->stringReplacer->getPersistenceValueForField($query, $criteriaExpression->value2, $mappingCollection);
        }

        return $sql;
    }
}
