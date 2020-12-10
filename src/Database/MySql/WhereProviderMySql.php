<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Query\CriteriaGroup;

/**
 * Provider of SQL for where clause on MySQL
 * @package Objectiphy\Objectiphy\Database\MySql
 */
class WhereProviderMySql extends AbstractSqlProvider
{
    /**
     * @param array $criteria
     * @return string The WHERE part of the SQL query.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getWhere(QueryInterface $query, array $objectNames, array $persistenceNames): string
    {
        $sql = ' WHERE 1';
        $removeJoiner = false;
        foreach ($query->getWhere() as $criteriaExpression) {
            if ($criteriaExpression instanceof CriteriaGroup) {
                $removeJoiner = $criteriaExpression->type != CriteriaGroup::GROUP_TYPE_END;
                $sql .= ' ' . (string) $criteriaExpression;
            } else {
                if (!$removeJoiner) {
                    $sql .= ' ' . $criteriaExpression->joiner;
                }
                $sql .= ' ' . $criteriaExpression->toString($this->params);
                $removeJoiner = false;
            }
        }
        array_walk($this->params, function(&$value) {
            $this->dataTypeHandler->toPersistenceValue($value);
        });
        $sql = str_replace($objectNames, $persistenceNames, $sql);

        return $sql;
    }
}
