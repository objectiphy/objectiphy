<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Query\CriteriaGroup;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for where clause on MySQL
 */
class WhereProviderMySql extends AbstractSqlProvider
{
    /**
     * The WHERE part of the SQL query.
     * @param QueryInterface $query
     * @param array $objectNames
     * @param array $persistenceNames
     * @return string
     */
    public function getWhere(QueryInterface $query, array $objectNames, array $persistenceNames): string
    {
        $sql = ' WHERE 1';
        $removeJoiner = false;
        foreach ($query->getWhere() as $index => $criteriaExpression) {
            if ($criteriaExpression instanceof CriteriaGroup) {
                $removeJoiner = $criteriaExpression->type != CriteriaGroup::GROUP_TYPE_END;
                if ($index == 0 && $criteriaExpression->type == CriteriaGroup::GROUP_TYPE_START_OR) {
                    //If first item is an OR group, change it to AND, otherwise it ORs with 1 and matches every record!
                    $sql .= ' AND ' . substr((string) $criteriaExpression, 2);
                } else {
                    $sql .= ' ' . (string) $criteriaExpression;
                }
            } else {
                if (!$removeJoiner) {
                    $sql .= ' ' . $criteriaExpression->joiner;
                }
                $sql .= ' ' . $criteriaExpression->toString($this->params);
                $removeJoiner = false;
            }
        }
        array_walk($this->params, function(&$value) {
            if (!$this->dataTypeHandler->toPersistenceValue($value)) {
                //We have an object - extract the primary key value if we can
                if ($value instanceof ObjectReferenceInterface) {
                    $pkValues = $value->getPkValues();
                } else {
                    $pkValues = $this->mappingCollection->getPrimaryKeyValues($value);
                }
                $value = $pkValues ? reset($pkValues) : null;
            }
        });
        $sql = str_replace($objectNames, $persistenceNames, $sql);

        return $sql;
    }
}
