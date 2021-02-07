<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

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
    private string $currentJoinTargetClass = '';
    private bool $isCustomJoin = false;

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
                $this->processCriteriaExpression($query, $joinPart);
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
        $this->currentJoinTargetClass = '';
        $this->isCustomJoin = false;
        $this->objectNames = $objectNames;
        $this->persistenceNames = $persistenceNames;
    }

    private function processJoinExpression(JoinExpression $joinPart): void
    {
        $this->currentJoinAlias = $joinPart->joinAlias;
        $this->currentJoinTargetClass = $joinPart->targetEntityClassName;
        $this->sql .= " " . ($joinPart->type ?: "LEFT") . " JOIN ";
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

    private function processCriteriaExpression(QueryInterface $query, CriteriaExpression $joinPart): void
    {
        $this->joiner = $this->joiner ? $joinPart->joiner : "    ON ";
        if (!$this->removeJoiner) {
            $this->sql .= $this->joiner . ' ';
        }
        $this->isCustomJoin = false;
        if ($this->currentJoinAlias && substr($this->currentJoinAlias, 0, 10) !== 'obj_alias_') {
            $this->isCustomJoin = true;
        }
        $propertyPath = $joinPart->property->getPropertyPath();
        $sourceJoinColumns = [];
        $targetJoinColumns = [];

        if ($this->isCustomJoin
            && substr($propertyPath, 0, strlen($this->currentJoinAlias) + 1) == $this->currentJoinAlias . '.') {
            $propertyPath = substr($propertyPath, strpos($propertyPath, '.') + 1);
            $column = $this->getSqlForField($query, $propertyPath);
            $sourceJoinColumns[] = $column;
        } elseif ($this->mappingCollection) {
            $this->getJoinColumns($propertyPath, $sourceJoinColumns, $targetJoinColumns);
        }

        if ($sourceJoinColumns && ($targetJoinColumns || $this->isCustomJoin)) {
            $joinSql = [];
            foreach ($sourceJoinColumns as $index => $sourceJoinColumn) {
                if ($index == 0 && $this->isCustomJoin) {
                    $targetJoinColumn = $this->getSqlForField($query, $joinPart->value);
                }  else {
                    $targetJoinColumn = $this->delimit($targetJoinColumns[$index] ?? '');
                }
                if ($targetJoinColumn) {
                    $joinSql[] = $this->delimit($sourceJoinColumn) . ' = ' . $targetJoinColumn;
                }
            }
            $this->sql .= implode("\n AND ", $joinSql) . "\n";
        } else {
            $this->sql .= "    " . str_replace($this->objectNames, $this->persistenceNames, $joinPart->toString($this->params)) . "\n";
        }
    }

    /**
     * @param string $propertyPath
     * @param array $sourceJoinColumns
     * @param array $targetJoinColumns
     * @throws MappingException
     */
    private function getJoinColumns(
        string $propertyPath,
        array &$sourceJoinColumns,
        array &$targetJoinColumns
    ): void {
        $joinPartPropertyMapping = $this->mappingCollection->getPropertyMapping(
            $propertyPath
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
                sprintf('No mapping information found for `%1$s`', $propertyPath)
            );
        }
    }
}
