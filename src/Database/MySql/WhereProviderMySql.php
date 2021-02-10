<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Orm\ObjectMapper;
use Objectiphy\Objectiphy\Query\CriteriaGroup;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for where clause on MySQL
 */
class WhereProviderMySql
{
    private ObjectMapper $objectMapper;
    private SqlStringReplacer $stringReplacer;

    public function __construct(SqlStringReplacer $stringReplacer, ObjectMapper $objectMapper)
    {
        $this->stringReplacer = $stringReplacer;
        $this->objectMapper = $objectMapper;
    }

    /**
     * The WHERE part of the SQL query.
     * @param QueryInterface $query
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function getWhere(QueryInterface $query): string
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
                $sql .= " " . $criteriaExpression->toString($query->getParams()) . "\n";
                $removeJoiner = false;
            }
        }
        $sql = trim($this->stringReplacer->replaceNames($sql));

        return $sql;
    }
}
