<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for select queries on MySQL
 */
class SqlSelectorMySql implements SqlSelectorInterface
{
    private SqlStringReplacer $stringReplacer;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;
    private bool $disableMySqlCache = false;
    private FindOptions $options;
    private SelectQueryInterface $query;

    public function __construct(
        SqlStringReplacer $stringReplacer,
        JoinProviderMySql $joinProvider,
        WhereProviderMySql $whereProvider
    ) {
        $this->stringReplacer = $stringReplacer;
        $this->joinProvider = $joinProvider;
        $this->whereProvider = $whereProvider;
    }

    /**
     * These are options that are likely to change on each call (unlike config options).
     * @param FindOptions $options
     */
    public function setFindOptions(FindOptions $options): void
    {
        $this->options = $options;
    }

    /**
     * In case you are being naughty and overriding things, you might need this.
     * @return FindOptions
     */
    public function getFindOptions(): FindOptions
    {
        return $this->options;
    }

    /**
     * Any config options that the fetcher needs to know about are set here.
     * @param bool $disableMySqlCache
     */
    public function setConfigOptions(bool $disableMySqlCache = false): void
    {
        $this->disableMySqlCache = $disableMySqlCache;
    }

    /**
     * Get the SQL query necessary to select the records that will be used to hydrate the given entity.
     * @param SelectQueryInterface $query
     * @return string The SQL query to execute.
     * @throws \Exception
     */
    public function getSelectSql(SelectQueryInterface $query): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Selector has not been initialised. There is no mapping information!');
        }
        $this->query = $query;
        $this->stringReplacer->prepareReplacements($query, $this->options->mappingCollection);    

        $sql = $this->getSelect();
        $sql .= $this->getFrom();
        $sql .= $this->joinProvider->getJoins($query);
        $originalParseDelimeterValue = $this->stringReplacer->parseDelimiters;
        $this->stringReplacer->parseDelimiters = $this->options->parseDelimiters;  
        $sql .= $this->whereProvider->getWhere($query, $this->options->mappingCollection);
        // leave stringReplacer how we found it to prevent possible side effects
        $this->stringReplacer->parseDelimiters = $originalParseDelimeterValue;
        $having = $this->whereProvider->getHaving($query, $this->options->mappingCollection);
        $sql .= $this->getGroupBy($having ? true : false);
        $sql .= $having;
        $sql .= $this->getOrderBy();
        $sql .= $this->getLimit();
        $sql .= $this->getOffset();

        if ($this->options->count && strpos($sql, 'SELECT COUNT') === false) { //We have to select all to get the count :(
            $sql = "/* count */\nSELECT COUNT(*) FROM (\n$sql\n) subquery";
        }

        if ($this->disableMySqlCache) {
            $sql = str_replace('SELECT ', 'SELECT SQL_NO_CACHE ', $sql);
        }
                
        return $sql;
    }

    /**
     * @return string The SELECT part of the SQL query.
     * @throws ObjectiphyException
     */
    public function getSelect(): string
    {
        $sql = $this->getCountSql();

        if (!$sql) {
            $sql = "/* select */\nSELECT \n";
            foreach ($this->query->getSelect() as $fieldExpression) {
                $fieldSql = trim((string) $fieldExpression);
                $usePreparedAlias = $fieldExpression->isPropertyPath() && !$fieldExpression->getAlias();
                $columnNameWithoutAlias = $this->stringReplacer->replaceNames($fieldSql);
                $fieldExpressionAlias = (!$usePreparedAlias && $fieldExpression->getAlias()) ? ' AS ' . $fieldExpression->getAlias() : '';
                $aliasSuffix = $fieldExpressionAlias ?: $this->stringReplacer->replaceNames($fieldSql, $usePreparedAlias, true);
                if ($fieldExpression->getDataMap()) {
                    $sql .= "    CASE\n";
                    foreach ($fieldExpression->getDataMap() as $key => $value) {
                        if (!$key || strtoupper(strval($key)) == 'ELSE') {
                            $sql .= "        ELSE ";
                        } else {
                            $sql .= "        WHEN $columnNameWithoutAlias ";
                            $sql .= ($value['operator'] ?? '=') . " '" . strval($key) . "'";
                            $sql .= " THEN ";
                        }
                        $sql .= "'" . strval(($value['value'] ?? $value)) . "'\n";
                    }
                    $sql .= "    END $aliasSuffix\n";
                } else {
                    $sql .= "    " . $columnNameWithoutAlias . $aliasSuffix;
                }
                $sql .= ", \n";
            }
            $sql = rtrim($sql, ", \n");
        }

        //We cannot count using a subquery if there are duplicate column names, and only need one column for the count to work
        if ($this->options->count && strpos($sql, 'SELECT COUNT(') === false) {
            $sql = substr($sql, 0, strpos($sql, ','));
        }

        return $sql;
    }

    /**
     * @return string The FROM part of the SQL query.
     * @throws ObjectiphyException
     */
    public function getFrom(): string
    {
        return "\n/* from */\nFROM " . $this->stringReplacer->replaceNames($this->query->getFrom());
    }

    /**
     * @return string The SQL string for the GROUP BY clause, if applicable.
     * @throws ObjectiphyException
     */
    public function getGroupBy(bool $usesHaving = false): string
    {
        $sql = '';
        $queryGroupBy = $this->query->getGroupBy();
        $aggregateGroupBy = $this->stringReplacer->getAggregateGroupBys();
        $foreignKeyGroupBy = [];
        if (($queryGroupBy && !$this->options->allowDuplicates) || $aggregateGroupBy || $usesHaving) {
            //As we are automatically adding groups, ensure all FKs are also grouped (MySQL strict mode)
            foreach ($this->query->getFields() as $field) {
                $propertyMapping = $this->options->mappingCollection->getPropertyMapping($field->getPropertyPath());
                if ($propertyMapping && ($propertyMapping->isForeignKey || $propertyMapping->column->isPrimaryKey)) {
                    //Must use table alias for children, or we risk grouping by a column that is not in the query
                    $foreignKeyGroupBy[] = $propertyMapping->getFullColumnName($propertyMapping->parents ? $propertyMapping->getTableAlias() : '');
                }
            }
        }
        $groupBy = array_unique(array_filter(array_merge($queryGroupBy, $aggregateGroupBy, $foreignKeyGroupBy)));
        if ($groupBy) {
            $sql = "\n/* groupBy */\nGROUP BY ";
            foreach ($groupBy as $index => $groupItem) {
                $groupBy[$index] = $this->stringReplacer->delimit($this->stringReplacer->replaceNames(strval($groupItem)));
            }
            $sql .= implode(', ', $groupBy);
        }

        return $sql;
    }

    /**
     * @return string The SQL string for the ORDER BY clause.
     * @throws ObjectiphyException
     */
    public function getOrderBy(): string
    {
        $sql = '';

        if (!$this->options->count) {
            if (!empty($orderBy = $this->query->getOrderBy())) {
                $sql .= "\n/* orderBy */\nORDER BY ";
                foreach ($this->query->getOrderBy() as $index => $orderByField) {
                    $sql .= $index > 0 ? ', ' : '';
                    $direction = $this->query->getOrderByDirections()[$index] ?? 'ASC';
                    $sql .= $this->stringReplacer->replaceNames(strval($orderByField)) . " $direction\n";
                }
            }
        }

        return $sql;
    }

    /**
     * @return string The SQL string for the LIMIT clause (if using pagination).
     */
    public function getLimit(): string
    {
        $sql = '';

        if (!$this->options->multiple) {
            $sql = "\n/* limit */\nLIMIT 1";
        } elseif (!$this->options->count && !empty($this->options->pagination)) {
            $sql = "\n/* limit */\nLIMIT " . $this->options->pagination->getRecordsPerPage();
        } elseif ($this->query->getLimit() ?? false) {
            $sql = "\n/* limit */\nLIMIT " . $this->query->getLimit();
        }

        return $sql;
    }

    /**
     * @return string The SQL string for the LIMIT offset (if using pagination).
     */
    public function getOffset(): string
    {
        $sql = '';
        if ($this->options->multiple && !$this->options->count) {
            if ($this->query->getOffset() ?? false) {
                $sql = "\n    /* offset */\n    OFFSET " . $this->query->getOffset();
            } elseif (!empty($this->options->pagination) && $this->options->pagination->getOffset()) {
                $sql = "\n    /* offset */\n    OFFSET " . $this->options->pagination->getOffset();
            }
        }

        return $sql;
    }

    /**
     * @return string
     * @throws ObjectiphyException
     */
    protected function getCountSql(): string
    {
        $sql = '';
        if ($this->options->count && empty($this->queryOverrides)) { //See if we can do a more efficient count
            if (!$this->getGroupBy()) { //If grouping, count must happen using a subquery (see getSelectSql, above)
                $sql = "/* count */\nSELECT COUNT(*) \n";
            }
        }

        return $sql;
    }
}
