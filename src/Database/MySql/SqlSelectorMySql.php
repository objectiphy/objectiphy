<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;

/**
 * Provider of SQL for select queries on MySQL
 * @package Objectiphy\Objectiphy\Database\MySql
 */
class SqlSelectorMySql extends AbstractSqlProvider implements SqlSelectorInterface
{
    protected array $objectNames = [];
    protected array $persistenceNames = [];
    protected array $aliases = [];

    private bool $disableMySqlCache = false;
    private FindOptions $options;
    private SelectQueryInterface $query;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;

    public function __construct(
        DataTypeHandlerInterface $dataTypeHandler, 
        JoinProviderMySql $joinProvider, 
        WhereProviderMySql $whereProvider
    ) {
        parent::__construct($dataTypeHandler);
        $this->joinProvider = $joinProvider;
        $this->whereProvider = $whereProvider;
    }

    /**
     * These are options that are likely to change on each call (unlike config options).
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
     */
    public function setConfigOptions(bool $disableMySqlCache = false): void
    {
        $this->disableMySqlCache = $disableMySqlCache;
    }

    /**
     * Get the SQL query necessary to select the records that will be used to hydrate the given entity.
     * @return string The SQL query to execute.
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
            $sql = "SELECT COUNT(*) FROM ($sql) subquery";
        }

        if ($this->disableMySqlCache) {
            $sql = str_replace('SELECT ', 'SELECT SQL_NO_CACHE ', $sql);
        }

        return $sql;
    }

    /**
     * @return string The SELECT part of the SQL query.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getSelect(): string
    {
        $this->countWithoutGroups = false;
        $sql = $this->getCountSql();
        
        if (!$sql) {
            $sql = 'SELECT ';
            foreach ($this->query->getSelect() as $fieldExpression) {
                $fieldSql = trim((string) $fieldExpression);
                $sql .= str_replace($this->objectNames, $this->aliases, $fieldSql) . ', ';
            }
            $sql = rtrim($sql, ', ');
        }

        //We cannot count using a subquery if there are duplicate column names, and only need one column for the count to work
        if ($this->options->count && strpos($sql, 'SELECT COUNT(') === false) {
            $sql = substr($sql, 0, strpos($sql, ','));
        }

        return $sql;
    }

    /**
     * @return string The FROM part of the SQL query.
     */
    public function getFrom(): string
    {
        $sql = ' FROM ' . $this->replaceNames($this->query->getFrom());
        return $sql;
    }

    /**
     * @return string The SQL string for the GROUP BY clause, if applicable.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getGroupBy($ignoreCount = false): string
    {
        //This function can be overridden, but baseGroupBy cannot be - we need to know whether the value is ours or not.
        return $this->baseGroupBy($ignoreCount);
    }

    /**
     * @return string The SQL string for the HAVING clause, if applicable (used where the criteria involves an
     * aggregate function, either directly in the criteria itself, or by comparing against a property that uses one.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getHaving(): string
    {
        $criteria = [];
        foreach ($this->query->getHaving() as $criteriaExpression) {
            $criteria[] = $this->replaceNames((string) $criteriaExpression);
        }
        
        return implode(' AND ', $criteria);
    }

    /**
     * @return string The SQL string for the ORDER BY clause.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getOrderBy(): string
    {
        $sql = '';

        if (!$this->options->count) {
            $orderBy = $this->query->getOrderBy();
            if (!empty($orderBy)) {
                $orderByString = ' ORDER BY ' . implode(', ', $orderBy);
                $sql = $this->replaceNames($orderByString);
            }
        }

        return $sql;
    }

    /**
     * @param array $criteria
     * @return string The SQL string for the LIMIT clause (if using pagination).
     */
    public function getLimit(): string
    {
        $sql = '';

        if (!$this->options->multiple) {
            $sql = ' LIMIT 1 ';
        } elseif (!$this->options->count && !empty($this->options->pagination)) {
            $sql = ' LIMIT ' . $this->options->pagination->getRecordsPerPage() . ' ';
        } elseif ($this->query->getLimit() ?? false) {
            $sql = ' LIMIT ' . $this->query->getLimit();
        }

        return $sql;
    }
    
    public function getOffset(): string
    {
        $sql = '';

        if ($this->options->multiple && !$this->options->count) {
            if ($this->query->getOffset() ?? false) {
                $sql = ' OFFSET ' . $this->query->getOffset();
            } elseif (!empty($this->options->pagination) && $this->options->pagination->getOffset()) {
                $sql = ' OFFSET ' . $this->options->pagination->getOffset();
            }
        }

        return $sql;
    }

    protected function getCountSql(): string
    {
        $sql = '';
        if ($this->options->count && empty($this->queryOverrides)) { //See if we can do a more efficient count
            $groupBy = trim(str_replace('GROUP BY', '', $this->getGroupBy(true)));
            $baseGroupBy = trim(str_replace('GROUP BY', '', $this->baseGroupBy(true)));
            if ($groupBy) {
                if (!$this->mappingCollection->hasAggregateFunctions() && $groupBy == $baseGroupBy) {
                    $sql .= "SELECT COUNT(DISTINCT " . $groupBy . ") ";
                    $this->countWithoutGroups = true;
                } // else: we do the full select, and use it as a sub-query - the count happens outside, in getSelect
            } else {
                $sql .= "SELECT COUNT(*) ";
                $this->countWithoutGroups = true;
            }
        }

        return $sql;
    }

    /**
     * @return string SQL string for the GROUP BY clause, base implementation (cannot be overridden).
     * @throws MappingException
     * @throws \ReflectionException
     */
    private function baseGroupBy(): string
    {
        $sql = '';

        $groupBy = $this->query->getGroupBy();
        if ($groupBy) {
            $sql = $this->replaceNames(implode(', ', $groupBy));
        }

        return $sql;
    }

    /**
     * Build arrays of strings to replace and what to replace them with.
     * @param string $delimiter
     */
    protected function prepareReplacements(
        MappingCollection $mappingCollection,
        string $delimiter = '`',
        $altDelimiter = '|'
    ): void {
        $this->sql = '';
        $this->objectNames = [];
        $this->persistenceNames = [];
        $this->aliases = [];

        $propertiesUsed = $this->query->getPropertyPaths();
        foreach ($propertiesUsed as $propertyPath) {
            $property = $mappingCollection->getPropertyMapping($propertyPath);
            if (!$property) {
                throw new QueryException('Property mapping not found for: ' . $propertyPath);
            }
            $this->objectNames[] = '`' . $property->getPropertyPath() . '`';
            $tableColumnString = $property->getFullColumnName();
            $this->persistenceNames[] = $this->delimit($tableColumnString, $delimiter);
            //Use alternative delimiter for aliases so we don't accidentally replace them
            $this->aliases[] = $this->delimit($property->getFullColumnName(), $altDelimiter)
                . ' AS ' . $this->delimit($property->getAlias(), $altDelimiter);
        }
        $tables = $mappingCollection->getTables();
        foreach ($tables as $class => $table) {
            $this->objectNames[] = $class;
            $this->persistenceNames[] = $this->delimit(str_replace($delimiter, '', $table->name)) ;
        }
    }

    protected function replaceNames(string $subject): string
    {
        if (!isset($this->objectNames)) {
            throw new ObjectiphyException('Please call prepareReplacements method before attempting to replace.');
        }

        return str_replace($this->objectNames, $this->persistenceNames, $subject);
    }
}
