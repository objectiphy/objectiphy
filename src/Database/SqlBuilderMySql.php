<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\QB;

class SqlBuilderMySql implements SqlBuilderInterface
{
    private bool $disableMySqlCache = false;
    private FindOptions $options;
    private string $mainTable;

    /**
     * These are options that are likely to change on each call (unlike config options).
     */
    public function setFindOptions(FindOptions $findOptions): void
    {
        $this->options = $findOptions;
        $this->mainTable = $findOptions->mappingCollection->getPrimaryTableMapping()->name;
    }

    /**
     * In case you are being naughty and overriding things, you might need this.
     * @return FindOptions
     */
    public function getFindOptions(): FindOptions
    {
        return $this->options;
    }

    public function setSaveOptions(): void
    {
        //None yet...
    }

    /**
     * Any config options that the fetcher needs to know about are set here.
     */
    public function setConfigOptions(bool $disableMySqlCache = false)
    {
        $this->disableMySqlCache = $disableMySqlCache;
    }

//    /** @var string */
//    protected $entityClassName;
//    /** @var array */
//    protected $params;
//    /** @var array */
//    protected $customWhereParams;
//    /** @var string */
//    protected $mainTable;
//    /** @var boolean Whether we can omit GROUP BY during count */
//    protected $countWithoutGroups = false;
//    /** @var boolean */
//    protected $groupByPrimaryKey = true;
//    /** @var string */
//    protected $recordAgeIndicator;
//    /** @var string */
//    protected $customWhereClause = '';
//    /** @var array SQL strings or closures to override the generated SQL query parts (keyed on part) */
//    protected $queryOverrides;
//    /** @var string */
//    protected $knownParentProperty;
//    /** @var string */
//    private $latestAlias;
//
//
//
//
//
//    /**
//     * Where returning the latest record from a group, the value supplied here will be used to determine which record
//     * in each group is the latest (defaults to primary key value).
//     * @param string $sqlFragment
//     */
//    public function setRecordAgeIndicator($sqlFragment)
//    {
//        $this->recordAgeIndicator = $sqlFragment;
//    }
//
//    /**
//     * Allows for custom criteria to be specified as an SQL WHERE clause.
//     * @param string $whereClause
//     * @param array $params
//     */
//    public function setCustomWhereClause($whereClause, array $params = [])
//    {
//        $this->customWhereClause = $whereClause;
//        $this->customWhereParams = $params;
//    }
//
//    /**
//     * @param PaginationInterface|null $pagination
//     */
//    public function setPagination(PaginationInterface $pagination = null)
//    {
//        $this->pagination = $pagination;
//    }
//
//    /**
//     * @param array|null $orderBy
//     */
//    public function setOrderBy(array $orderBy = null)
//    {
//        $this->orderBy = $orderBy;
//    }
//
//    /**
//     * @param bool $value Whether or not to group by primary key to prevent duplicate records being returned.
//     * NOTE: This may cause an error if MySQL's only_full_group_by is enabled (which it is by default in 5.7.5 onwards)
//     */
//    public function setAllowDuplicates($value)
//    {
//        $this->groupByPrimaryKey = $value ? false : true;
//    }
//
//    /**
//     * @param string $parentProperty We don't bother getting data that is already known
//     */
//    public function setKnownParentProperty($parentProperty)
//    {
//        $this->knownParentProperty = $parentProperty;
//    }
//
//    /**
//     * @param array $queryOverrides
//     */
//    public function overrideQueryParts(array $queryOverrides)
//    {
//        $this->queryOverrides = $queryOverrides;
//    }

    /**
     * Get the SQL query necessary to select the records that will be used to hydrate the given entity.
     * @return string The SQL query to execute.
     */
    public function getSelectQuery()
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Builder has not been initialised. There is no mapping information!');
        }
        if (empty($this->mainTable)) {
            throw new ObjectiphyException('Mapping collection does not have a primary table defined.');
        }

        $sql = $this->getSelect()
            . $this->getFrom()
            . $this->getJoinsForLatestRecord()
            . $this->getJoins()
            . $this->getWhere()
            . $this->getGroupBy()
            . $this->getHaving()
            . $this->getOrderBy()
            . $this->getLimit()
            . $this->getOffset();

        if ($this->options->count && strpos($sql, 'SELECT COUNT') === false) { //We have to select all to get the count :(
            $sql = "SELECT COUNT(*) FROM ($sql) subquery";
        }

        if ($this->disableMySqlCache) {
            $sql = str_replace('SELECT ', 'SELECT SQL_NO_CACHE ', $sql);
        }

        return $sql;
    }

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
        $sql = $this->getCountSql($this->options->getCriteria());
        
        if (!$sql) {
            $columns = [];
            $columnDefinitions = $this->options->mappingCollection->getColumnDefinitions();
            foreach ($columnDefinitions as $alias => $propertyMapping) {
                if ($columnName = $propertyMapping->getFullColumnName()) {
                    $columns[] = $this->delimit($columnName) . ' AS ' . $this->delimit($alias);
                }
            }
            $sql = "SELECT " . ($columns ? implode(', ', $columns) . " " : "* ");
        }

        $selectSql = $this->overrideQueryPart('select', $sql, $this->getQueryParams());

        //We cannot count using a subquery if there are duplicate column names, and only need one column for the count to work
        if ($this->options->count && strpos($selectSql, 'SELECT COUNT(') === false) {
            $selectSql = substr($selectSql, 0, strpos($selectSql, ','));
        }

        return $selectSql;
    }

    /**
     * @return string The FROM part of the SQL query.
     */
    public function getFrom()
    {
        $sql = "FROM " . $this->delimit($this->mainTable);

        return $this->overrideQueryPart('from', $sql, $this->getQueryParams());
    }

    /**
     * @return string The join SQL for returning the latest record(s) in a group.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getJoinsForLatestRecord()
    {
        $sql = '';
//        if ($this->latest && $this->objectMapper->getCommonShortColumn()) {
//            //Join on itself to get latest record per common column
//            $sql .= " LEFT JOIN `" . $this->mainTable . "` $this->latestAlias   
//                ON (" . $this->delimit(
//                    $this->mainTable . "." . $this->objectMapper->getCommonShortColumn()
//                ) . " = " . $this->delimit(
//                    $this->latestAlias . "." . $this->objectMapper->getCommonShortColumn()
//                );
//            if ($this->recordAgeIndicator) { //Custom indicator for which record is the latest (eg. by a datetime field, or coalesce on more than one column)
//                if (strpos($this->recordAgeIndicator, $this->mainTable) === false) {
//                    throw new MappingException(
//                        'Record age indicator does not contain the fully qualified main table name (' . $this->mainTable . ').'
//                    );
//                }
//                $sql .= " AND " . $this->recordAgeIndicator . " < " . str_replace(
//                        $this->mainTable,
//                        $this->latestAlias,
//                        $this->recordAgeIndicator
//                    ) . ")";
//            } else { //Default to assuming the largest id is the latest record
//                $sql .= " AND " . $this->delimit(
//                        $this->mainTable . "." . $this->objectMapper->getIdColumn(false)
//                    ) . " < " . $this->delimit(
//                        $this->latestAlias . "." . $this->objectMapper->getIdColumn(false)
//                    ) . ") ";
//            }
//        }

        return $this->overrideQueryPart('joinsforlatestrecord', $sql, $this->getQueryParams());
    }

    /**
     * @return string The join SQL for object relationships.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getJoins()
    {
        $sql = '';
        $relationshipProperties = $this->options->mappingCollection->getRelationships();
        foreach ($relationshipProperties as $propertyMapping) {
            if ($propertyMapping->relationship->isLateBound()) {
                continue;
            }
            if ($this->options->count
                && !$this->options->getCriteria()
                && ($propertyMapping->relationship->joinType ?: 'LEFT') == 'LEFT'
            ) {
                continue; //No need for left joins if there is no criteria and we are just counting records
            }
            $sourceJoinColumns = $propertyMapping->getSourceJoinColumns();
            $targetJoinColumns = $propertyMapping->getTargetJoinColumns();
            if (!$propertyMapping->relationship->joinSql && !$sourceJoinColumns && !$targetJoinColumns) {
                //Shouldn't happen, but if it does, don't try to add it, as we know for sure the SQL is invalid
                continue;
            }
            $sql .= $this->buildJoinSql($propertyMapping, $sourceJoinColumns, $targetJoinColumns);
        }

        return $this->overrideQueryPart('joins', $sql, $this->getQueryParams());
    }

    private function buildJoinSql(PropertyMapping $propertyMapping, array $sourceJoinColumns, array $targetJoinColumns): string
    {
        $sql = " " . ($propertyMapping->relationship->joinType ?: 'LEFT') . " JOIN ";
        $sql .= $this->delimit($propertyMapping->relationship->joinTable) . " ";
        $sql .= $this->delimit($propertyMapping->getTableAlias(true));
        $sql .= " ON ";
        if ($propertyMapping->relationship->joinSql) {
            $sql .= $propertyMapping->relationship->joinSql;
        } else {
            $joinSql = [];
            foreach ($sourceJoinColumns as $index => $sourceJoinColumn) {
                $joinSql[] = $this->delimit($sourceJoinColumn) . ' = ' . $this->delimit($targetJoinColumns[$index]);
            }
            $sql .= implode(' AND ', $joinSql);
        }

        return $sql;
    }

    /**
     * @param array $criteria
     * @return string The WHERE part of the SQL query.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getWhere()
    {
        $this->params = [];
        $sql = ' WHERE 1 ';

        if ($this->options->latest) {
            //$sql .= " AND $this->latestAlias." . $this->objectMapper->getIdColumn(false) . " IS NULL ";
        }

        foreach ($this->options->getCriteria() as $criteriaExpression) {
            $sql .= $this->applyCriteria($criteriaExpression, 'AND');
        }

        //$sql .= $this->customWhereClause ? " AND ($this->customWhereClause) " : "";

        return $this->overrideQueryPart('where', $sql, $this->getQueryParams());
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
        //$criteria = QB::create()->normalize($criteria); //As method is public, we have to normalize

        $having = '';
//        $joinMappings = $this->objectMapper->getJoinMappings($this->entityClassName, $criteria);
//        foreach ($criteria as $criteriaExpression) {
//            $having .= $this->applyCriteria($criteriaExpression, 'AND', true, $joinMappings);
//        }
        $sql = $having ? ' HAVING 1 ' . $having : '';

        return $this->overrideQueryPart('having', $sql, $this->getQueryParams());
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
            $orderBy = $this->options->getOrderBy();

            if ($orderBy === null) {
                //See if we can order by primary key of main entity
                $pkProperties = $this->options->mappingCollection->getPrimaryKeyProperties(true);
                $orderBy = array_combine($pkProperties, array_fill(0, count($pkProperties), 'ASC'));

            }
            if (!empty($orderBy)) {
                $sql = " ORDER BY ";
                foreach ($orderBy as $property => $direction) {
                    $propertyMapping = $this->options->mappingCollection->getPropertyMapping($property);
                    if ($propertyMapping) {
//                        if ($propertyMapping->aggregateFunction) {
//                            $classMapping = $this->objectMapper->getClassMapping($this->entityClassName);
//                            $sql .= $this->objectMapper->constructAggregateFunction($classMapping, $propertyMapping);
//                        } else {
                            $sql .= $propertyMapping->getFullColumnName();
//                        }
                        $sql .= " " . strtoupper($direction) . ',';
                    }
                }
                $sql = rtrim($sql, ',');
            }
        }

        return $this->overrideQueryPart('orderby', $sql, $this->getQueryParams());
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
        }

        return $this->overrideQueryPart('limit', $sql, $this->getQueryParams());
    }
    
    public function getOffset()
    {
        $sql = '';

        if ($this->options->multiple
            && !$this->options->count
            && !empty($this->options->pagination) 
            && $this->options->pagination->getOffset()
        ) {
            $sql .= ' OFFSET ' . $this->options->pagination->getOffset() . ' ';
        }

        return $this->overrideQueryPart('offset', $sql, $this->getQueryParams());
    }

    public function setQueryParams(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Return the parameter values to bind to the SQL statement. Where more than one SQL statement is involved, the
     * index identifies which one we are dealing with.
     * @param int|null $index Index of the SQL query.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams($index = null)
    {
        $params = [];
        if ($index !== null && ($index != 0 || isset($this->params[$index]))) {
            $params = $this->params[$index] ?: [];
        } else {
            $params = !empty($this->params) ? $this->params : [];
        }

        return $params;
    }

    /**
     * Get the SQL statements necessary to insert the given row.
     * @param array $row The row to insert.
     * @param bool $replace Whether or not to update the row if the primary key already exists.
     * @return array An array of SQL queries to execute for inserting this record (base implementation will always
     * return a single SQL statement, but extended classes might need to execute multiple queries).
     */
    public function getInsertQueries(array $row, $replace = false)
    {
        $this->params = [];

        if (!empty($row['table']) && !empty($row['data'])) {
            $sql = "INSERT INTO " . $this->delimit($row['table']) . " SET ";
            $assignments = '';
            foreach ($row['data'] as $column => $value) {
                $value = $value instanceof ObjectReferenceInterface ? $value->getPrimaryKeyValue() : $value;
                $paramName = 'param_' . strval(count($this->params));
                $assignments .= $this->delimit($column) . " = :" . $paramName . ',';
                $this->params[$paramName] = $value;
            }
            $assignments = rtrim($assignments, ",");
            $sql .= $assignments;
            if ($replace || !empty($row['isScalarJoin'])) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . $assignments;
            }

            return [$this->overrideQueryPart('insert', $sql, [], $this->params)];
        }

        return [];
    }

    /**
     * This is just an alias of getInsertQueries, for backward compatibility purposes
     * @param array $row
     * @param bool $replace
     * @return array
     */
    public function getInsertSql(array $row, $replace = false)
    {
        return $this->getInsertQueries($row, $replace);
    }

    /**
     * Get the SQL statements necessary to update the given row record.
     * @param string $entityClassName Name of the parent entity class for the record being updated (used to get the
     * primary key column).
     * @param array $row Row of data to update.
     * @param mixed $keyValue Value of primary key for record to update.
     * @param string $fullKeyColumn
     * @return array An array of SQL queries to execute for updating the entity.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getUpdateQueries($entityClassName, $row, $keyValue, $fullKeyColumn = '')
    {
        $this->params = [];

        if (!empty($row['table']) && !empty($row['data'])) {
            $sql = (!empty($row['isScalarJoin']) ? "INSERT INTO " : "UPDATE ") . $this->delimit(
                    $row['table']
                ) . " SET ";
            $assignments = '';
            foreach ($row['data'] as $column => $value) {
                $value = $value instanceof ObjectReferenceInterface ? $value->getPrimaryKeyValue() : $value;
                $paramName = 'param_' . strval(count($this->params));
                $assignments .= $this->delimit($column) . " = :" . $paramName . ',';
                $this->params[$paramName] = $value;
            }
            $assignments = rtrim($assignments, ",");
            $sql .= $assignments;
            $paramName = 'param_' . strval(count($this->params));
            if (!empty($row['isScalarJoin'])) {
                $sql .= " ON DUPLICATE KEY UPDATE " . $assignments;
            } else {
                $this->params[$paramName] = $keyValue;
                $sql .= ' WHERE ' . $this->delimit(
                        $fullKeyColumn ?: $this->objectMapper->getIdColumn(true, $entityClassName)
                    ) . ' = :' . $paramName;
            }

            return [$this->overrideQueryPart('update', $sql, [], $this->params)];
        }

        return [];
    }

    /**
     * @param $childClassName
     * @param $parentKeyPropertyName
     * @param $parentKeyPropertyValue
     * @return string
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getForeignKeysQuery($childClassName, $parentKeyPropertyName, $parentKeyPropertyValue)
    {
        $sql = '';
        $classMapping = $this->objectMapper->getClassMapping($childClassName);
        $tableName = $classMapping->tableName;
        $childPkMapping = $classMapping->getPrimaryKeyPropertyMapping();
        $childPkColumn = $childPkMapping ? $childPkMapping->getFullColumnName() : '';
        $columnName = '';

        foreach ($classMapping->getPropertyMappings() as $propertyMapping) {
            if ($propertyMapping->propertyName == $parentKeyPropertyName) {
                $columnName = $propertyMapping->getFullColumnName();
                break;
            }
        }

        if ($tableName && $childPkColumn && $columnName) {
            $sql = "SELECT $childPkColumn FROM $tableName WHERE $columnName = :parentKeyValue";
            $this->params = ['parentKeyValue' => $parentKeyPropertyValue];
        }

        return $sql;
    }

    /**
     * @param string $entityClassName Class name of entity being removed
     * @param mixed $keyValue Value of primary key for record to delete.
     * @return array An array of queries to execute for removing the entity.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getDeleteQueries($entityClassName, $keyValue)
    {
        $sql = null;
        $classMapping = $this->objectMapper->getClassMapping($entityClassName);
        $tableName = $classMapping->tableName;
        $pkPropertyMapping = $classMapping->getPrimaryKeyPropertyMapping();
        $columnName = $pkPropertyMapping->getFullColumnName();

        if ($tableName && $columnName && $keyValue !== null) {
            $sql = "DELETE FROM $tableName WHERE $columnName = :pkValue";
            $this->params = ['pkValue' => $keyValue];
        }

        return array_filter([$sql]);
    }

    /**
     * Replace prepared statement parameters with actual values (for debugging output only, not for execution!)
     * @param string $query Parameterised SQL string
     * @param array $params Parameter values to replace tokens with
     * @return string SQL string with values instead of parameters
     */
    public function replaceTokens($query, $params)
    {
        if (count($params)) {
            foreach (
                array_reverse(
                    $params
                ) as $key => $value
            ) { //Don't want to replace param_10 with column name for param_1 followed by a zero!
                $query = str_replace(
                    ':' . $key,
                    ($value === null || $value === true || $value === false ? var_export($value, true) : "'$value'"),
                    $query
                );
            }
        }

        return $query;
    }

    protected function getCountSql(array $criteria = [])
    {
        $sql = '';
        if ($this->options->count && empty($this->queryOverrides)) { //See if we can do a more efficient count
            $groupBy = trim(str_replace('GROUP BY', '', $this->getGroupBy($criteria, true)));
            $baseGroupBy = trim(str_replace('GROUP BY', '', $this->baseGroupBy($criteria, true)));
            if ($groupBy) {
                if (!$this->mappingCollection->hasAggregateFunctions() && $groupBy == $baseGroupBy) {
                    $sql .= "SELECT COUNT(DISTINCT " . $groupBy . ") ";
                    $this->countWithoutGroups = true;
                } // else: we do the full select, and use it as a sub-query - the count happens outside, in getSelectQuery
            } else {
                $sql .= "SELECT COUNT(*) ";
                $this->countWithoutGroups = true;
            }
        }

        return $sql;
    }

    /**
     * Build an SQL expression based on an Objectiphy CriteriaExpression object.
     * @param CriteriaExpression|array $expression The line of criteria to convert into SQL.
     * @param string $joiner How to join this set of criteria with any previous criteria.
     * @param bool $having
     * @param array $joinMappings
     * @return string SQL for the criteria expression.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    protected function applyCriteria($expression, $joiner = 'AND', $having = false, $joinMappings = [])
    {
        $sql = '';

        if ($expression instanceof CriteriaExpression) {
            $value = $expression->getCriteriaValue();
            $value2 = $expression->getCriteriaValue2();
            $operator = $expression->getCriteriaOperator() ?: '=';
            if ($value !== null || $operator == 'IS' || $operator == 'IS NOT') { //Only filter if value supplied
                $propertyMapping = $this->options->mappingCollection->getPropertyMapping($expression->propertyName);
                $columnName = $propertyMapping->getFullColumnName();
                $format = $propertyMapping->column->format;
                $isDateString = in_array($propertyMapping->column->type, ['datetimestring', 'datestring']);
                $isDateString = $isDateString ?: strpos(strtolower($propertyMapping->propertyName), 'date') !== false;
                $dateFormat = $isDateString ? $format : '';
                $sql .= $joiner . ' (' . $this->applyCriteriaValue($columnName, $operator, $value, $value2, $dateFormat);

//                $parentClassMapping = null;
//                $propertyMapping = $this->objectMapper->getPropertyMapping(
//                    $this->entityClassName,
//                    $expression->propertyName,
//                    true,
//                    $parentClassMapping
//                );
//                $joinMappingAlias = $this->objectMapper->joinMapper->getJoinMappingKeyForPropertyPath(
//                    $this->entityClassName,
//                    $expression->propertyName,
//                    $joinMappings,
//                    $parentClassMapping ? $parentClassMapping->tableName : ''
//                );
//                if ($propertyMapping) {
//                    $applyCriteria = ($having && ($expression->aggregateFunction || $propertyMapping->aggregateFunction))
//                        || (!$having && !$expression->aggregateFunction && !$propertyMapping->aggregateFunction);
//                    if ($applyCriteria) { //Criteria involving aggregate functions use HAVING
//                        if ($propertyMapping->aggregateFunction) { //Find the correct alias for the collection
//                            $aggregateCollectionClass = $parentClassMapping->properties[$propertyMapping->aggregateCollection]->dataType;
//                            $aggregateCollectionPropertyPathParts = explode('.', $expression->propertyName);
//                            array_pop($aggregateCollectionPropertyPathParts);
//                            $aggregateCollectionPropertyPath = implode(
//                                '.',
//                                array_merge(
//                                    $aggregateCollectionPropertyPathParts,
//                                    explode(
//                                        '.',
//                                        $propertyMapping->aggregateCollection
//                                    )
//                                )
//                            );
//                            $joinMappingAlias = $having ? $this->objectMapper->joinMapper->getJoinMappingKeyForPropertyPath(
//                                $this->entityClassName,
//                                $aggregateCollectionPropertyPath,
//                                $joinMappings
//                            ) : '';
//                            $aggregatePropertyMapping = $this->objectMapper->getPropertyMapping(
//                                $aggregateCollectionClass,
//                                $propertyMapping->aggregateProperty
//                            );
//                            $columnName = $aggregatePropertyMapping->getFullColumnName(true, $joinMappingAlias);
//                            $columnName = $propertyMapping->aggregateFunction . '(' . $columnName . ')';
//                        } else {
//                            $tableAlias = $joinMappingAlias ?: (!$propertyMapping->tableName && $parentClassMapping ? $parentClassMapping->tableName : '');
//                            $columnPrefix = $this->objectMapper->getColumnPrefixForPropertyPath(
//                                $expression->propertyName
//                            );
//                            $columnName = $propertyMapping->getFullColumnName(
//                                true,
//                                $tableAlias,
//                                false,
//                                false,
//                                $columnPrefix
//                            ); //Embedded objects do not have a table name - use parent
//                            if ($expression->aggregateFunction) {
//                                $columnName = $expression->aggregateFunction . '(' . $columnName . ')';
//                            }
//                        }
//                        $dateFormat = $propertyMapping->format && (in_array(
//                                $propertyMapping->dataType,
//                                ['datetimestring', 'datestring']
//                            ) || strpos(
//                                strtolower($propertyMapping->propertyName),
//                                'date'
//                            ) !== false) ? $propertyMapping->format : '';
//                        $sql = " $joiner (" . $this->applyCriteriaValue(
//                                $columnName,
//                                $operator,
//                                $value,
//                                $value2,
//                                $dateFormat
//                            );
//                    }
                    foreach ($expression->andExpressions as $andExpression) {
                        $sql .= $this->applyCriteria($andExpression, 'AND', $having, $joinMappings);
                    }
                    foreach ($expression->orExpressions as $orExpression) {
                        $sql .= $this->applyCriteria($orExpression, 'OR', $having, $joinMappings);
                    }
                    $sql .= ')';
//                }
            }
        }

        return $sql;
    }

    /**
     * Builds the tokens for the prepared statement for the given column, operator, and value(s).
     * @param string $columnName
     * @param string $operator
     * @param mixed $value
     * @param mixed|null $value2 Only needed for BETWEEN operator.
     * @return string The SQL for the expression using tokens for a prepared statement.
     */
    protected function applyCriteriaValue($columnName, $operator, $value, $value2 = null, $dateFormat = '')
    {
        $sql = $columnName;
        $this->convertDateValues($value, $dateFormat);
        $this->convertDateValues($value2, $dateFormat);
        if (trim($operator) == 'IN' || trim($operator) == 'NOT IN') {
            //Special case - array of values
            $value = is_array($value) || $value instanceof \Traversable ? $value : [$value];
            $sql .= " $operator (";
            foreach ($value as $element) {
                $this->params['param_' . (count($this->params) + 1)] = $element;
                $sql .= ":param_" . count($this->params) . ",";
            }
            $sql = rtrim($sql, ',') . ')';
        } else {
            //Most operators work on a single value
            if ($this->resolveValue($value)) {
                //Not prepared - direct reference to another column
                $sql .= " $operator " . $value;
            } else {
                $this->params['param_' . (count($this->params) + 1)] = $value;
                $sql .= " $operator :param_" . count($this->params);
            }
            if ($operator == 'BETWEEN') {
                //Between works on two values
                if ($this->resolveValue($value2)) {
                    $sql .= " AND " . $value2;
                } else {
                    $this->params['param_' . (count($this->params) + 1)] = $value2;
                    $sql .= " AND :param_" . count($this->params);
                }
            }
        }

        return $sql;
    }

    private function convertDateValues(&$value, $dateFormat)
    {
        if (is_array($value)) {
            foreach ($value as $valueItem) {
                $this->convertDateValues($valueItem, $dateFormat);
            }
        } elseif ($value && $dateFormat) {
            $dateTime = \DateTime::createFromFormat('!' . ltrim($dateFormat, '!'), $value);
            if ($dateTime) {
                $value = $dateTime->format('Y-m-d H:i:s') ?: $value;
            }
        }
    }

    /**
     * If value is surrounded by backticks, attempt to resolve it to a table/column, otherwise, assume an explicit value
     * @param $value
     */
    private function resolveValue(&$value)
    {
        $strValue = (string) $value;
        if (substr($strValue, 0, 1) == '`' && substr($strValue, -1) == '`') {
            $columnName = $this->objectMapper->getColumnForPropertyPath(str_replace('`', '', $strValue));
            if ($columnName) {
                $value = $columnName;
                return true;
            }
        }

        return false;
    }

    /**
     * Convert "database.table.column" to "`database`.`table`.`column`". As the input does not
     * come from a user, but from mapping definitions, we will not sanitize in case there is a
     * reason a for a developer wanting to break out of the backticks to do something filthy.
     * @param string $tableColumnString Database/Table/Column separated by a dot.
     * @return string Backtick separated string equivalent.
     */
    private function delimit($tableColumnString)
    {
        $delimited = '';
        if ($tableColumnString) {
            $delimited = "`" . implode("`.`", explode('.', $tableColumnString)) . "`";
        }

        return $delimited;
    }

    /**
     * @param bool $ignoreCount
     * @return string SQL string for the GROUP BY clause, base implementation (cannot be overridden).
     * @throws MappingException
     * @throws \ReflectionException
     */
    private function baseGroupBy($ignoreCount = false)
    {
        $sql = '';

        if ($ignoreCount || !$this->countWithoutGroups) {
//            $sql .= " GROUP BY ";
//            $groups = [];
//            $aggregateGroupByColumns = $this->objectMapper->getAggregateGroupByColumns();
//            if ($aggregateGroupByColumns) {
//                $groups = array_merge($groups, $aggregateGroupByColumns);
//            }
//            if ($this->latest) {
//                //Getting latest record
//                $groups[] = $this->delimit($this->mainTable . '.' . $this->objectMapper->getCommonShortColumn());
//            } elseif ($this->groupByPrimaryKey) {
//                $groups[] = $this->delimit($this->objectMapper->getIdColumn());
//            }
//            if (!empty(array_filter($groups))) {
//                $sql .= ' ' . implode(', ', array_unique($groups)) . ' ';
//            } else {
//                $sql = '';
//            }
        }

        return $sql;
    }

    private function overrideQueryPart($part, $generatedQuery, $params)
    {
        $override = !empty($this->queryOverrides[strtolower($part)])
            ? $this->queryOverrides[strtolower($part)]
            : $generatedQuery;
        if (is_callable($override)) {
            $override = call_user_func_array($override, [$generatedQuery, $this->options->criteria, $params]);
        } elseif (!is_string($override)) { //We don't know what the heck this is - just use our generated query
            $override = $generatedQuery;
        }

        return $override;
    }
}
