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
        $sql .= $this->whereProvider->getWhere($query, $this->options->mappingCollection);
        $sql .= $this->getGroupBy();
        $sql .= $this->getHaving();
        $sql .= $this->getOrderBy();
        $sql .= $this->getLimit();
        $sql .= $this->getOffset();

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
                $sql .= "    " . $this->stringReplacer->replaceNames($fieldSql, true) . ", \n";
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
        return "FROM " . $this->stringReplacer->replaceNames($this->query->getFrom()) . "\n";
    }

    /**
     * @return string The SQL string for the GROUP BY clause, if applicable.
     * @throws ObjectiphyException
     */
    public function getGroupBy(): string
    {
        $sql = '';
        $groupBy = $this->query->getGroupBy();
        if ($groupBy) {
            $sql = $this->stringReplacer->replaceNames(implode(', ', $groupBy)) . "\n";
        }

        return $sql;
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
            $criteria[] = $this->stringReplacer->replaceNames((string) $criteriaExpression);
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
                $sql = $this->stringReplacer->replaceNames($orderByString) . "\n";
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
            if (!$this->getGroupBy()) { //If grouping, count must happen using a subquery (see getSelectSql, above)
                $sql .= "SELECT COUNT(*) ";
            }
        }

        return $sql;
    }
}
