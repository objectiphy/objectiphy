<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\JoinExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for joins on MySQL
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
     * @param QueryInterface $query
     * @param array $objectNames
     * @param array $persistenceNames
     * @return string The join SQL for object relationships.
     */
    public function getJoins(QueryInterface $query, array $objectNames, array $persistenceNames): string
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

    private function initialise(array $objectNames, array $persistenceNames): void
    {
        $this->sql = '';
        $this->joiner = null;
        $this->removeJoiner = false;
        $this->currentJoinAlias = '';
        $this->isCustomJoin = false;
        $this->objectNames = $objectNames;
        $this->persistenceNames = $persistenceNames;
    }

    private function processJoinExpression(JoinExpression $joinPart): void
    {
        $this->currentJoinAlias = $joinPart->joinAlias;
        $this->sql .= ($joinPart->type ?: "LEFT") . " JOIN ";
        if ($joinPart->propertyMapping && $joinPart->propertyMapping->relationship->joinTable) {
            $this->sql .= $this->delimit($joinPart->propertyMapping->relationship->joinTable);
        } else {
            $this->sql .= str_replace($this->objectNames, $this->persistenceNames, $joinPart->targetEntityClassName);
        }
        $this->sql .= " " . $this->delimit($this->currentJoinAlias) . "\n";
        $this->joiner = null;
    }

    private function processCriteriaGroup(CriteriaGroup $joinPart): void
    {
        $this->removeJoiner = $joinPart->type != CriteriaGroup::GROUP_TYPE_END;
        $this->sql .= '    ' . str_replace($this->objectNames, $this->persistenceNames, (string) $joinPart) . "\n";
    }

    private function processCriteriaExpression(CriteriaExpression $joinPart): void
    {
        $this->joiner = $this->joiner ? $joinPart->joiner : "    ON ";
        if (!$this->removeJoiner) {
            $this->sql .= $this->joiner;
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
                    if (!(preg_match("/(\s|\%|\')/", $targetJoinColumn))) {
                        //Assume it is a column, not a literal value
                        $targetJoinColumn = $this->delimit($targetJoinColumn);
                    }
                }  else {
                    $targetJoinColumn = $this->delimit($targetJoinColumns[$index] ?? '');
                }
                if ($targetJoinColumn) {
                    $joinSql[] = $this->delimit($sourceJoinColumn) . ' = ' . $targetJoinColumn;
                }
            }
            $this->sql .= implode("\n AND ", $joinSql) . "\n";
        } else {
            $this->sql .= "    " . $joinPart->toString($this->params) . "\n";
        }
    }

    /**
     * @param JoinPartInterface $joinPart
     * @param array $sourceJoinColumns
     * @param array $targetJoinColumns
     * @throws MappingException
     */
    private function getJoinColumns(
        JoinPartInterface $joinPart,
        array &$sourceJoinColumns,
        array &$targetJoinColumns
    ): void {
        $joinPartPropertyMapping = $this->mappingCollection->getPropertyMapping(
            $joinPart->property->getPropertyPath()
        );
        if ($joinPartPropertyMapping) {
            $sourceJoinColumns = $joinPartPropertyMapping->getSourceJoinColumns();
            $targetJoinColumns = $joinPartPropertyMapping->getTargetJoinColumns();
            if (count($sourceJoinColumns) != 1 && !$this->isCustomJoin //Don't need target for custom join
                && ((empty($sourceJoinColumns) && empty($targetJoinColumns))
                || (count($sourceJoinColumns) != count($targetJoinColumns)))
            ) {
                throw new MappingException(
                    sprintf(
                        'Relationship mapping for %1$s::%2$s is incomplete.',
                        $joinPartPropertyMapping->className,
                        $joinPartPropertyMapping->propertyName
                    )
                );
            }
        } else {
            throw new MappingException(
                sprintf('No mapping information found for `%1$s`', $joinPart->property->getPropertyPath())
            );
        }
    }
}
