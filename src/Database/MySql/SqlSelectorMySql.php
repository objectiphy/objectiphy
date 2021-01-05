<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for select queries on MySQL
 */
class SqlSelectorMySql extends SqlProviderMySql implements SqlSelectorInterface
{
    private bool $disableMySqlCache = false;
    private FindOptions $options;

    /**
     * These are options that are likely to change on each call (unlike config options).
     * @param FindOptions $options
     */
    public function setFindOptions(FindOptions $options): void
    {
        $this->options = $options;
        $this->setMappingCollection($options->mappingCollection);
        $this->joinProvider->setMappingCollection($options->mappingCollection);
        $this->whereProvider->setMappingCollection($options->mappingCollection);
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
        $this->params = [];
        $this->prepareReplacements($this->options->mappingCollection, '`', '|');

        $sql = $this->getSelect();
        $sql .= $this->getFrom();
        $this->joinProvider->setQueryParams($this->params);
        $sql .= $this->joinProvider->getJoins($this->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->joinProvider->getQueryParams());
        $this->whereProvider->setQueryParams($this->params);
        $sql .= $this->whereProvider->getWhere($this->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->whereProvider->getQueryParams());
        $sql .= $this->getGroupBy();
        $sql .= $this->getHaving();
        $sql .= $this->getOrderBy();
        $sql .= $this->getLimit();
        $sql .= $this->getOffset();

        $sql = str_replace('|', '`', $sql); //Revert to backticks now the replacements are done.

        if ($this->options->count && strpos($sql, 'SELECT COUNT') === false) { //We have to select all to get the count :(
            $sql = "SELECT COUNT(*) FROM (\n$sql\n) subquery";
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
            $sql = "SELECT \n";
            foreach ($this->query->getSelect() as $fieldExpression) {
                $fieldSql = trim((string) $fieldExpression);
                $sql .= "    " . str_replace($this->objectNames, $this->aliases, $fieldSql) . ", \n";
            }
            $sql = rtrim($sql, ", \n") . "\n";
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
        return "FROM " . $this->replaceNames($this->query->getFrom()) . "\n";
    }

    /**
     * @param bool $ignoreCount
     * @return string The SQL string for the GROUP BY clause, if applicable.
     * @throws ObjectiphyException
     */
    public function getGroupBy($ignoreCount = false): string
    {
        //This function can be overridden, but baseGroupBy cannot be - we need to know whether the value is ours or not.
        return $this->baseGroupBy($ignoreCount);
    }

    /**
     * @return string The SQL string for the HAVING clause, if applicable (used where the criteria involves an
     * aggregate function, either directly in the criteria itself, or by comparing against a property that uses one.
     * @throws ObjectiphyException
     */
    public function getHaving(): string
    {
        $criteria = [];
        foreach ($this->query->getHaving() as $criteriaExpression) {
            $criteria[] = $this->replaceNames((string) $criteriaExpression);
        }

        return implode("\nAND ", $criteria) . "\n";
    }

    /**
     * @return string The SQL string for the ORDER BY clause.
     * @throws ObjectiphyException
     */
    public function getOrderBy(): string
    {
        $sql = '';

        if (!$this->options->count) {
            $orderBy = $this->query->getOrderBy();
            if (!empty($orderBy)) {
                $orderByString = 'ORDER BY ' . implode(', ', $orderBy);
                $sql = $this->replaceNames($orderByString) . "\n";
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
            $sql = "LIMIT 1 \n";
        } elseif (!$this->options->count && !empty($this->options->pagination)) {
            $sql = "LIMIT " . $this->options->pagination->getRecordsPerPage() . " \n";
        } elseif ($this->query->getLimit() ?? false) {
            $sql = "LIMIT " . $this->query->getLimit() . "\n";
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
                $sql = '    OFFSET ' . $this->query->getOffset() . "\n";
            } elseif (!empty($this->options->pagination) && $this->options->pagination->getOffset()) {
                $sql = '    OFFSET ' . $this->options->pagination->getOffset() . "\n";
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
            $groupBy = trim(str_replace('GROUP BY', '', $this->getGroupBy(true)));
            $baseGroupBy = trim(str_replace('GROUP BY', '', $this->baseGroupBy(true)));
            if ($groupBy) {
                if (!$this->mappingCollection->hasAggregateFunctions() && $groupBy == $baseGroupBy) {
                    $sql .= "SELECT COUNT(DISTINCT " . $groupBy . ") ";
                } // else: we do the full select, and use it as a sub-query - the count happens outside, in getSelect
            } else {
                $sql .= "SELECT COUNT(*) ";
            }
        }

        return $sql;
    }

    /**
     * @return string SQL string for the GROUP BY clause, base implementation (cannot be overridden).
     * @throws ObjectiphyException
     */
    private function baseGroupBy(): string
    {
        $sql = '';

        $groupBy = $this->query->getGroupBy();
        if ($groupBy) {
            $sql = $this->replaceNames(implode(', ', $groupBy)) . "\n";
        }

        return $sql;
    }

    /**
     * @param string $subject
     * @return string
     * @throws ObjectiphyException
     */
    protected function replaceNames(string $subject): string
    {
        if (!isset($this->objectNames)) {
            throw new ObjectiphyException('Please call prepareReplacements method before attempting to replace.');
        }

        return str_replace($this->objectNames, $this->persistenceNames, $subject);
    }
}
