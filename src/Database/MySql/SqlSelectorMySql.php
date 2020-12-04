<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\CriteriaGroup;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Contract\SqlSelectorInterface;

use Objectiphy\Objectiphy\Query\Query;

use function PHPUnit\Framework\returnValue;

class SqlSelectorMySql extends AbstractSqlProvider implements SqlSelectorInterface
{
    private bool $disableMySqlCache = false;
    private FindOptions $options;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;

    public function __construct(JoinProviderMySql $joinProvider, WhereProviderMySql $whereProvider)
    {
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
    public function setConfigOptions(bool $disableMySqlCache = false)
    {
        $this->disableMySqlCache = $disableMySqlCache;
    }

    /**
     * Get the SQL query necessary to select the records that will be used to hydrate the given entity.
     * @return string The SQL query to execute.
     */
    public function getSelectSql(): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Builder has not been initialised. There is no mapping information!');
        }

        $this->params = [];
        $this->prepareReplacements($this->options->query, $this->options->mappingCollection, '`', '|');

        $sql = $this->getSelect();
        $sql .= $this->getFrom();
        $this->joinProvider->setQueryParams($this->params);
        $sql .= $this->joinProvider->getJoins($this->options->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->joinProvider->getQueryParams());
        $this->whereProvider->setQueryParams($this->params);
        $sql .= $this->whereProvider->getWhere($this->options->query, $this->objectNames, $this->persistenceNames);
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

//    public function getQueryParams(): array
//    {
//        $whereParams = $this->whereProvider->getQueryParams();
//        $joinParams = array_merge($this->params, $this->joinProvider->getQueryParams());
//        $thisParams = array_merge($this->params, parent::getQueryParams());
//
//        return $this->combineParams($whereParams, $joinParams, $thisParams);
//    }

    /**
     * @param array $criteria
     * @return string The SELECT part of the SQL query.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getSelect()
    {
        $this->countWithoutGroups = false;
        $sql = $this->getCountSql();
        
        if (!$sql) {
            $sql = 'SELECT ';
            foreach ($this->options->query->getSelect() as $fieldExpression) {
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
    public function getFrom()
    {
        $sql = ' FROM ' . $this->replaceNames($this->options->query->getFrom());
        return $sql;
    }

    /**
     * @return string The SQL string for the GROUP BY clause, if applicable.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getGroupBy($ignoreCount = false)
    {
        //This function can be overridden, but baseGroupBy cannot be - we need to know whether the value is ours or not.
        return $this->baseGroupBy($ignoreCount);
    }

    /**
     * @param array $criteria
     * @return string The SQL string for the HAVING clause, if applicable (used where the criteria involves an
     * aggregate function, either directly in the criteria itself, or by comparing against a property that uses one.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getHaving()
    {
        $sql = '';
        return $sql;
    }

    /**
     * @param array $criteria
     * @return string The SQL string for the ORDER BY clause.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getOrderBy()
    {
        $sql = '';

        if (!$this->options->count) {
            $orderBy = $this->options->query->getOrderBy();
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
    public function getLimit()
    {
        $sql = '';

        if (!$this->options->multiple) {
            $sql = ' LIMIT 1 ';
        } elseif (!$this->options->count && !empty($this->options->pagination)) {
            $sql = ' LIMIT ' . $this->options->pagination->getRecordsPerPage() . ' ';
        } elseif ($this->options->query->getLimit() ?? false) {
            $sql = ' LIMIT ' . $this->options->query->getLimit();
        }

        return $sql;
    }
    
    public function getOffset()
    {
        $sql = '';

        if ($this->options->multiple && !$this->options->count) {
            if (!empty($this->options->pagination) && $this->options->pagination->getOffset()) {
                $sql = ' OFFSET ' . $this->options->pagination->getOffset();
            } elseif ($this->options->query->getOffset() ?? false) {
                $sql = ' OFFSET ' . $this->options->query->getOffset();
            }
        }

        return $sql;
    }

    protected function getCountSql()
    {
        $sql = '';
        if ($this->options->count && empty($this->queryOverrides)) { //See if we can do a more efficient count
            $groupBy = trim(str_replace('GROUP BY', '', $this->getGroupBy(true)));
            $baseGroupBy = trim(str_replace('GROUP BY', '', $this->baseGroupBy(true)));
            if ($groupBy) {
                if (!$this->mappingCollection->hasAggregateFunctions() && $groupBy == $baseGroupBy) {
                    $sql .= "SELECT COUNT(DISTINCT " . $groupBy . ") ";
                    $this->countWithoutGroups = true;
                } // else: we do the full select, and use it as a sub-query - the count happens outside, in getQuery
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
    private function baseGroupBy()
    {
        $sql = '';

        $groupBy = $this->options->query->getGroupBy();
        if ($groupBy) {
            $sql = $this->replaceNames(implode(', ', $groupBy));
        }

        return $sql;
    }

    protected $objectNames;
    protected $persistenceNames;
    protected $aliases;
    /**
     * Build arrays of strings to replace and what to replace them with.
     * @param string $delimiter
     */
    protected function prepareReplacements(
        Query $query,
        MappingCollection $mappingCollection,
        string $delimiter = '`',
        $altDelimiter = '|'
    ) {
        $this->sql = '';
        $this->objectNames = [];
        $this->persistenceNames = [];
        $this->aliases = [];

        $propertiesUsed = $query->getPropertyPaths();
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

    protected function replaceNames(string $subject)
    {
        if (!isset($this->objectNames)) {
            throw new ObjectiphyException('Please call prepareReplacements method before attempting to replace.');
        }

        return str_replace($this->objectNames, $this->persistenceNames, $subject);
    }
}
