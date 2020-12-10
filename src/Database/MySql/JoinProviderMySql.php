<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\JoinExpression;

/**
 * Provider of SQL for joins on MySQL
 * @package Objectiphy\Objectiphy\Database\MySql
 */
class JoinProviderMySql extends AbstractSqlProvider
{
    private ?string $joiner = null;
    private bool $removeJoiner = false;
    private string $currentJoinAlias = '';
    private bool $isCustomJoin = false;
    private array $objectNames = [];
    private array $persistenceNames = [];

    /**
     * @return string The join SQL for object relationships.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getJoins(QueryInterface $query, array $objectNames, array $persistenceNames)
    {
        $this->initialise($objectNames, $persistenceNames);
        foreach ($query->getJoins() as $joinPart) {
            if ($joinPart instanceof JoinExpression) {
                $this->processJoinExpression($joinPart);
            } elseif ($joinPart instanceof CriteriaGroup) {
                $this->processCriteriaGroup($joinPart);
            } elseif ($joinPart instanceof CriteriaExpression) {
                $this->processCriteriaExpression($joinPart);
            }
        }

        return $this->sql;
    }

    private function initialise(array $objectNames, array $persistenceNames)
    {
        $this->sql = '';
        $this->joiner = null;
        $this->removeJoiner = false;
        $this->currentJoinAlias = '';
        $this->isCustomJoin = false;
        $this->objectNames = $objectNames;
        $this->persistenceNames = $persistenceNames;
    }

    private function processJoinExpression(JoinExpression $joinPart)
    {
        $this->currentJoinAlias = $joinPart->joinAlias;
        $this->sql .= " " . ($joinPart->type ?: 'LEFT') . " JOIN ";
        $this->sql .= str_replace($this->objectNames, $this->persistenceNames, $joinPart->targetEntityClassName);
        $this->sql .= ' ' . $this->delimit($this->currentJoinAlias);
        $this->joiner = null;
    }

    private function processCriteriaGroup(CriteriaGroup $joinPart)
    {
        $this->removeJoiner = $joinPart->type != CriteriaGroup::GROUP_TYPE_END;
        $this->sql .= ' ' . str_replace($this->objectNames, $this->persistenceNames, (string) $joinPart);
    }

    private function processCriteriaExpression(CriteriaExpression $joinPart)
    {
        $this->joiner = $this->joiner ? $joinPart->joiner : " ON ";

        if (!$this->removeJoiner) {
            $this->sql .= ' ' . $this->joiner;
        }
        $this->isCustomJoin = false;
        if ($this->currentJoinAlias && substr($this->currentJoinAlias, 0, 10) !== 'obj_alias_') {
            $this->isCustomJoin = true;
        }
        if ($this->mappingCollection) {
            $sourceJoinColumns = [];
            $targetJoinColumns = [];
            $this->getJoinColumns($joinPart, $sourceJoinColumns, $targetJoinColumns);
            $joinSql = [];
            foreach ($sourceJoinColumns as $index => $sourceJoinColumn) {
                if ($index == 0 && $this->isCustomJoin) {
                    $targetJoinColumn = $joinPart->value;
                }  else {
                    $targetJoinColumn = $targetJoinColumns[$index] ?? '';
                }
                if ($targetJoinColumn) {
                    $joinSql[] = $this->delimit($sourceJoinColumn) . ' = ' . $this->delimit($targetJoinColumn);
                }
            }
            $this->sql .= implode(' AND ', $joinSql);
        } else {
            $this->sql .= ' ' . $joinPart->toString($this->params);
        }
        $removeJoiner = false;
    }

    private function getJoinColumns(JoinPartInterface $joinPart, array &$sourceJoinColumns, array &$targetJoinColumns)
    {
        $joinPartPropertyMapping = $this->mappingCollection->getPropertyMapping(
            $joinPart->property->getPropertyPath()
        );
        $sourceJoinColumns = $joinPartPropertyMapping->getSourceJoinColumns();
        $targetJoinColumns = $joinPartPropertyMapping->getTargetJoinColumns();

        if ((!$sourceJoinColumns && !$targetJoinColumns)
            || (count($sourceJoinColumns) != count($targetJoinColumns))
        ) {
            throw new MappingException(
                sprintf(
                    'Relationship mapping for %1$s::%2$s is incomplete.',
                    $joinPartPropertyMapping->className,
                    $joinPartPropertyMapping->propertyName
                )
            );
        }
    }
}
