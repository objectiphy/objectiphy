<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Orm\ObjectMapper;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\JoinExpression;
use Objectiphy\Objectiphy\Query\QB;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for joins on MySQL
 */
class JoinProviderMySql
{
    private SqlStringReplacer $stringReplacer;
    private ObjectMapper $objectMapper;
    private string $sql = '';
    private ?string $joiner = null;
    private bool $removeJoiner = false;

    public function __construct(SqlStringReplacer $stringReplacer, ObjectMapper $objectMapper)
    {
        $this->stringReplacer = $stringReplacer;
        $this->objectMapper = $objectMapper;
    }

    /**
     * @param QueryInterface $query
     * @return string The join SQL for object relationships.
     * @throws MappingException
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function getJoins(QueryInterface $query): string
    {
        $this->initialise();
        foreach ($query->getJoins() as $joinPart) {
            if ($joinPart instanceof JoinExpression) {
                $this->processJoinExpression($joinPart);
            } elseif ($joinPart instanceof CriteriaGroup) {
                $this->processCriteriaGroup($joinPart);
            } elseif ($joinPart instanceof CriteriaExpression) {
                $this->processCriteriaExpression($query, $joinPart);
            }
        }

        return $this->sql ? "\n" . trim($this->stringReplacer->replaceNames($this->sql)) : '';
    }

    private function initialise(): void
    {
        $this->sql = "/* joins */\n";
        $this->joiner = null;
        $this->removeJoiner = false;
    }

    private function processJoinExpression(JoinExpression $joinPart): void
    {
        $this->sql .= " " . ($joinPart->type ?: "LEFT") . " JOIN ";
        if ($joinPart->propertyMapping && $joinPart->propertyMapping->relationship->joinTable) {
            $this->sql .= $this->stringReplacer->delimit($joinPart->propertyMapping->relationship->joinTable);
        } else {
            $this->sql .= $this->stringReplacer->replaceNames($joinPart->targetEntityClassName);
        }
        $this->sql .= " " . $this->stringReplacer->delimit($joinPart->joinAlias) . "\n";
        $this->joiner = null;
    }

    private function processCriteriaGroup(CriteriaGroup $joinPart): void
    {
        $this->removeJoiner = $joinPart->type != CriteriaGroup::GROUP_TYPE_END;
        $this->sql .= '    ' . $this->stringReplacer->replaceNames((string) $joinPart) . "\n";
    }

    /**
     * @param QueryInterface $query
     * @param CriteriaExpression $joinPart
     * @throws MappingException
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function processCriteriaExpression(QueryInterface $query, CriteriaExpression $joinPart): void
    {
        $this->joiner = $this->joiner ? $joinPart->joiner : "    ON ";
        if (!$this->removeJoiner) {
            $this->sql .= $this->joiner . ' ';
        } else {
            $this->removeJoiner = false; //Once removed, any further items will need joining
        }

        $propertyPath = $joinPart->property->getPropertyPath();
        $propertyAlias = strtok($propertyPath, '.');
        $propertyUsesAlias = $propertyAlias && $query->getClassForAlias($propertyAlias) ? true : false;
        $joinPartValues = is_array($joinPart->value) ? $joinPart->value : [$joinPart->value];
        foreach ($joinPartValues as $joinPartValue) {
            if ($valueAlias = strtok(strval($joinPartValue), '.')) {
                $valueUsesAlias = $query->getClassForAlias($valueAlias) ? true : false;
                if ($valueUsesAlias) {
                    break;
                }
            }
        }

        $mappingCollection = $this->objectMapper->getMappingCollectionForClass($query->getClassName());
        $joinPartPropertyMapping = $mappingCollection->getPropertyMapping($propertyPath);
        if ($joinPartPropertyMapping) {
            if (!$propertyUsesAlias) {
                $sourceJoinColumns = array_map(
                    function ($sourceColumn) {
                        return $sourceColumn ? $this->stringReplacer->delimit($sourceColumn) : '';
                    },
                    $joinPartPropertyMapping->getSourceJoinColumns()
                );
            }
            if (!$valueUsesAlias) {
                $targetJoinColumns = array_map(
                    function ($targetColumn) {
                        return $targetColumn ? $this->stringReplacer->delimit($targetColumn) : '';
                    },
                    $joinPartPropertyMapping->getTargetJoinColumns()
                );
            }
        }

        if (empty($sourceJoinColumns)) {
            $sourceJoinColumns[] = $this->stringReplacer->getPersistenceValueForField($query, $propertyPath ?: $joinPart->property->getExpression(), $mappingCollection);
        }
        if (empty($targetJoinColumns)) {
            $targetJoinColumns[] = $this->stringReplacer->getPersistenceValueForField($query, $joinPart->value, $mappingCollection);
        }

        if (empty($sourceJoinColumns) || count($sourceJoinColumns ?? []) != count($targetJoinColumns ?? [])) {
            throw new MappingException(
                sprintf(
                    'Relationship mapping for %1$s::%2$s is incomplete.',
                    $joinPartPropertyMapping->className,
                    $joinPartPropertyMapping->propertyName
                )
            );
        }

        $joinSql = [];
        foreach ($sourceJoinColumns as $index => $sourceJoinColumn) {
            $joinSqlString = $this->replaceAliases($sourceJoinColumn, $query) . ' ' . $joinPart->operator . ' ';
            if (strpos($joinPart->operator, QB::IN) !== false && is_array($targetJoinColumns[$index])) {
                $joinSql[] = $joinSqlString . '(' . implode(',', $targetJoinColumns[$index]) . ')';
            } elseif (is_string($targetJoinColumns[$index])) {
                $joinSql[] = $joinSqlString .$this->replaceAliases($targetJoinColumns[$index], $query);
            } else {
                throw new QueryException('Could not resolve join SQL for ' . strval($joinPart));
            }
        }
        $this->sql .= implode("\n AND ", $joinSql) . "\n";
    }

    /**
     * Where reference has been made to a column on a table which is only ever loaded via an alias,
     * use the alias (typically scalar joins and joinSql).
     * @param $column
     * @param QueryInterface $query
     * @return mixed|string
     * @throws ObjectiphyException
     */
    private function replaceAliases($column, QueryInterface $query)
    {
        if (strpos($column, '.') !== false) {
            $rootTable = $this->stringReplacer->replaceNames($query->getClassName());
            $columnParts = explode(' ', $column);
            foreach ($columnParts ?? [] as $index => $columnPart) {
                $aliased = '';
                if (strpos($columnPart, '.') !== false) {
                    $delimitedColumn = $this->stringReplacer->delimit($columnPart);
                    $searchString = substr($columnPart, 0, strrpos($columnPart, '.'));
                    if ($rootTable != $searchString) {
                        foreach ($query->getJoinAliases() as $alias => $realColumn) {
                            $delimitedRealColumn = $this->stringReplacer->delimit($realColumn);
                            $delimitedAlias = $this->stringReplacer->delimit($alias);
                            if ($searchString == $delimitedRealColumn) {
                                $aliased = $alias . '.' . substr($columnPart, strrpos($columnPart, '.') + 1);
                                break;
                            }
                        }
                    }
                }
                $columnParts[$index] = $aliased ?: $columnPart;
            }
            return implode(' ', $columnParts);
        }

        return $column;
    }
}
