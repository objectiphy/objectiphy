<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

class QueryBuilderMySql implements QueryBuilderInterface
{
    /** @var ObjectMapper */
    public $objectMapper; //Public so we can replace it with a clone when lazy loading
    /** @var boolean */
    public $multiple = true;
    /** @var Pagination */
    protected $pagination;
    /** @var array As per Doctrine, but with properties of children also allowed, eg. ['contact.lastName'=>'ASC',
     * 'policyNo'=>'DESC'] */
    protected $orderBy = [];
    /** @var string */
    protected $entityClassName;
    /** @var boolean */
    protected $latest = false;
    /** @var array */
    protected $params;
    /** @var array */
    protected $customWhereParams;
    /** @var string */
    protected $mainTable;
    /** @var boolean Whether we are counting records */
    protected $count = false;
    /** @var boolean Whether we can omit GROUP BY during count */
    protected $countWithoutGroups = false;
    /** @var boolean */
    protected $groupByPrimaryKey = true;
    /** @var string */
    protected $recordAgeIndicator;
    /** @var string */
    protected $customWhereClause = '';
    /** @var array SQL strings or closures to override the generated SQL query parts (keyed on part) */
    protected $queryOverrides;
    /** @var string */
    protected $knownParentProperty;
    /** @var string */
    private $latestAlias;
    /** @var bool */
    private $disableMySqlCache = false;

    public function __construct($disableMySqlCache = false)
    {
        $this->disableMySqlCache = $disableMySqlCache;
    }

    public function initialise(string $className, MappingCollection $mappingCollection)
    {
        
    }

    /**
     * Where returning the latest record from a group, the value supplied here will be used to determine which record
     * in each group is the latest (defaults to primary key value).
     * @param string $sqlFragment
     */
    public function setRecordAgeIndicator($sqlFragment)
    {
        $this->recordAgeIndicator = $sqlFragment;
    }

    /**
     * Allows for custom criteria to be specified as an SQL WHERE clause.
     * @param string $whereClause
     * @param array $params
     */
    public function setCustomWhereClause($whereClause, array $params = [])
    {
        $this->customWhereClause = $whereClause;
        $this->customWhereParams = $params;
    }

    /**
     * @param PaginationInterface|null $pagination
     */
    public function setPagination(PaginationInterface $pagination = null)
    {
        $this->pagination = $pagination;
    }

    /**
     * @param array|null $orderBy
     */
    public function setOrderBy(array $orderBy = null)
    {
        $this->orderBy = $orderBy;
    }

    /**
     * @param bool $value Whether or not to group by primary key to prevent duplicate records being returned.
     * NOTE: This may cause an error if MySQL's only_full_group_by is enabled (which it is by default in 5.7.5 onwards)
     */
    public function setAllowDuplicates($value)
    {
        $this->groupByPrimaryKey = $value ? false : true;
    }

    /**
     * @param string $parentProperty We don't bother getting data that is already known
     */
    public function setKnownParentProperty($parentProperty)
    {
        $this->knownParentProperty = $parentProperty;
    }

    /**
     * @param array $queryOverrides
     */
    public function overrideQueryParts(array $queryOverrides)
    {
        $this->queryOverrides = $queryOverrides;
    }

    /**
     * Get the SQL query necessary to select the records that will be used to hydrate the given entity.
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param bool $multiple Whether we are fetching multiple records or limiting to a single record.
     * @param bool $latest Whether or not to just return the latest record from each group.
     * @param bool $count Whether we just want to count the records.
     * @return string The SQL query to execute.
     * @throws MappingException
     * @throws \ReflectionException
     * @throws Exception\CriteriaException
     */
    public function getSelectQuery(array $criteria = [], $multiple = true, $latest = false, $count = false)
    {
        $this->multiple = $multiple;
        $this->latest = $latest;
        $this->count = $count;
        $this->objectMapper->overrideLateBindings($this->entityClassName, $criteria + $this->orderBy);
        $this->mainTable = $this->objectMapper->getClassMapping($this->entityClassName)->tableName;
        if (!$this->mainTable) {
            throw new MappingException(
                'Could not locate table for entity ' . $this->entityClassName . '. Please ensure you have specified a database table in the mapping definition for the entity.'
            );
        }
        if ($latest) {
            if (!$this->objectMapper->getCommonShortColumn()) {
                throw new MappingException('Cannot get latest record(s) - unable to determine common column.');
            }
            $this->latestAlias = $this->delimitColumns(str_replace('.', '_', $this->mainTable) . "_latest_alias");
        }

        $this->objectMapper->getJoinMappings($this->entityClassName, $criteria);
        $sqlParts = [
            $this->getSelect($criteria),
            $this->getFrom($criteria),
            $this->getJoinsForLatestRecord($criteria),
            $this->getJoins($criteria),
            $this->getWhere($criteria),
            $this->getGroupBy($criteria),
            $this->getHaving($criteria),
            $this->getOrderBy($criteria),
            $this->getLimit($criteria),
        ];

        $sql = implode(' ', $sqlParts);
        if ($count && strpos($sql, 'SELECT COUNT') === false) { //We have to select all to get the count :(
            $sql = "SELECT COUNT(*) FROM ($sql) subquery";
        }

        if ($this->disableMySqlCache) {
            $sql = str_replace('SELECT ', 'SELECT SQL_NO_CACHE ', $sql);
        }

        return $sql;
    }

    /**
     * This is just an alias of getSelectQuery, for backward compatibility purposes
     * @param array $criteria
     * @param bool $multiple
     * @param bool $latest
     * @param bool $count
     * @return string
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getSelectSql(array $criteria = [], $multiple = true, $latest = false, $count = false)
    {
        return $this->getSelectQuery($criteria, $multiple, $latest, $count);
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

        return array_merge($params, $this->customWhereParams ?: []);
    }

    /**
     * This is just an alias of getQueryParams, for backward compatibility purposes
     * @param null $index
     * @return array
     * @deprecated
     */
    public function getSqlParams($index = null)
    {
        return $this->getQueryParams($index);
    }

    public function setQueryParams(array $params = [])
    {
        $this->params = $params;
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
            $sql = "INSERT INTO " . $this->delimitColumns($row['table']) . " SET ";
            $assignments = '';
            foreach ($row['data'] as $column => $value) {
                $value = $value instanceof ObjectReferenceInterface ? $value->getPrimaryKeyValue() : $value;
                $paramName = 'param_' . strval(count($this->params));
                $assignments .= $this->delimitColumns($column) . " = :" . $paramName . ',';
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
            $sql = (!empty($row['isScalarJoin']) ? "INSERT INTO " : "UPDATE ") . $this->delimitColumns(
                    $row['table']
                ) . " SET ";
            $assignments = '';
            foreach ($row['data'] as $column => $value) {
                $value = $value instanceof ObjectReferenceInterface ? $value->getPrimaryKeyValue() : $value;
                $paramName = 'param_' . strval(count($this->params));
                $assignments .= $this->delimitColumns($column) . " = :" . $paramName . ',';
                $this->params[$paramName] = $value;
            }
            $assignments = rtrim($assignments, ",");
            $sql .= $assignments;
            $paramName = 'param_' . strval(count($this->params));
            if (!empty($row['isScalarJoin'])) {
                $sql .= " ON DUPLICATE KEY UPDATE " . $assignments;
            } else {
                $this->params[$paramName] = $keyValue;
                $sql .= ' WHERE ' . $this->delimitColumns(
                        $fullKeyColumn ?: $this->objectMapper->getIdColumn(true, $entityClassName)
                    ) . ' = :' . $paramName;
            }

            return [$this->overrideQueryPart('update', $sql, [], $this->params)];
        }

        return [];
    }

    /**
     * This is just an alias of getUpdateQueries, for backward compatibility purposes
     * @param $entityClassName
     * @param $row
     * @param $keyValue
     * @return array
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getUpdateSql($entityClassName, $row, $keyValue)
    {
        return $this->getUpdateQueries($entityClassName, $row, $keyValue);
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

    /**
     * @param array $criteria
     * @return string The SELECT part of the SQL query.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getSelect(array $criteria = [])
    {
        $sql = '';
        $this->countWithoutGroups = false;

        if ($this->count && empty($this->queryOverrides)) {
            $groupBy = trim(str_replace('GROUP BY', '', $this->getGroupBy($criteria, true)));
            $baseGroupBy = trim(str_replace('GROUP BY', '', $this->baseGroupBy($criteria, true)));
            if ($groupBy) {
                if (!$this->objectMapper->hasAggregateFunctions() && $groupBy == $baseGroupBy) {
                    $sql .= "SELECT COUNT(DISTINCT " . $groupBy . ") ";
                    $this->countWithoutGroups = true;
                } // else: we do the full select, and use it as a sub-query - the count happens outside, in getSelectQuery
            } else {
                $sql .= "SELECT COUNT(*) ";
                $this->countWithoutGroups = true;
            }
        }

        if (!$sql) {
            //If different instances of the same entity appear on different parent entities, ensure we pick up the correct aliases
            $topLevelPrefix = strtolower(str_replace('\\', '_', ltrim($this->entityClassName, '\\')));
            $columns = $this->objectMapper->getColumnsForClass(
                $this->entityClassName,
                $topLevelPrefix,
                $this->knownParentProperty
            );
            $sql = "SELECT " . ($columns ? implode(', ', $columns) . " " : "* ");
        }

        $selectSql = $this->overrideQueryPart('select', $sql, $criteria, $this->getQueryParams());

        //We cannot count using a subquery if there are duplicate column names, and only need one column for the count to work
        if ($this->count && strpos($selectSql, 'SELECT COUNT(') === false) {
            $selectSql = substr($selectSql, 0, strpos($selectSql, ','));
        }

        return $selectSql;
    }

    /**
     * @param array $criteria
     * @return string The FROM part of the SQL query.
     */
    public function getFrom(array $criteria = [])
    {
        $sql = "FROM " . $this->delimitColumns($this->mainTable);

        return $this->overrideQueryPart('from', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * @param array $criteria
     * @return string The join SQL for returning the latest record(s) in a group.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getJoinsForLatestRecord(array $criteria = [])
    {
        $sql = '';
        if ($this->latest && $this->objectMapper->getCommonShortColumn()) {
            //Join on itself to get latest record per common column
            $sql .= " LEFT JOIN " . $this->delimitColumns($this->mainTable) . " $this->latestAlias   
                ON (" . $this->delimitColumns(
                    $this->mainTable . "." . $this->objectMapper->getCommonShortColumn()
                ) . " = " . $this->delimitColumns(
                    $this->latestAlias . "." . $this->objectMapper->getCommonShortColumn()
                );
            if ($this->recordAgeIndicator) { //Custom indicator for which record is the latest (eg. by a datetime field, or coalesce on more than one column)
                if (strpos($this->recordAgeIndicator, $this->mainTable) === false) {
                    throw new MappingException(
                        'Record age indicator does not contain the fully qualified main table name (' . $this->mainTable . ').'
                    );
                }
                $sql .= " AND " . $this->recordAgeIndicator . " < " . str_replace(
                        $this->mainTable,
                        $this->latestAlias,
                        $this->recordAgeIndicator
                    ) . ")";
            } else { //Default to assuming the largest id is the latest record
                $sql .= " AND " . $this->delimitColumns(
                        $this->mainTable . "." . $this->objectMapper->getIdColumn(false)
                    ) . " < " . $this->delimitColumns(
                        $this->latestAlias . "." . $this->objectMapper->getIdColumn(false)
                    ) . ") ";
            }
        }

        return $this->overrideQueryPart('joinsforlatestrecord', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * @param array $criteria
     * @return string The join SQL for object relationships.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getJoins(array $criteria = [])
    {
        $sql = '';
        $criteria = CB::create()->normalize($criteria); //As method is public, we have to normalize
        $joinMappings = $this->objectMapper->getJoinMappings($this->entityClassName, $criteria);

        foreach ($joinMappings as $tableOrAlias => $joinMapping) {
            $propertyMapping = $joinMapping->propertyMapping;
            if ($propertyMapping) {
                if ($this->count && !$criteria && ($propertyMapping->joinType ?: 'LEFT' == 'LEFT')) {
                    continue; //No need for left joins if there is no criteria and we are just counting records
                }
                $joinedClassMapping = $joinMapping->joinedClassMapping;
                $targetJoinTable = $propertyMapping->joinTable ?: $joinedClassMapping->tableName;
                $alias = $targetJoinTable == $tableOrAlias ? '' : $tableOrAlias;
                if ($propertyMapping->isScalarJoin(false)) {
                    //Do the scalar join sql...
                    $sql .= " " . ($propertyMapping->joinType ?: 'LEFT') . " JOIN " . $this->delimitColumns(
                            $targetJoinTable
                        ) . " $alias ON ";
                    $columnPrefix = strlen(
                        $joinedClassMapping->tableName
                    ) == 0 ? $this->objectMapper->joinMapper->getColumnPrefix($joinMapping) : '';
                    $sql .= ($propertyMapping->joinSql ?: $propertyMapping->getFullColumnName(
                            true,
                            $joinMapping->tableOrAlias ?: $targetJoinTable,
                            false,
                            true,
                            $columnPrefix
                        ) . " = " . $propertyMapping->getFullColumnName(
                            true,
                            $alias,
                            $propertyMapping->targetJoinColumn
                        ));
                } elseif ($joinedClassMapping && ($propertyMapping->joinSql || $propertyMapping->getFullColumnName(
                        )) && $propertyMapping->targetJoinColumn) {
                    //Normal join for a parent/child relationship
                    $sql .= " " . ($propertyMapping->joinType ?: 'LEFT') . " JOIN " . $this->delimitColumns(
                            $targetJoinTable
                        ) . " $alias ON ";
                    $sql .= ($propertyMapping->joinSql ?: $propertyMapping->getFullColumnName(
                            true,
                            $joinMapping->tableOrAlias
                        ) . " = " . $this->delimitColumns(
                            ($alias ?: $targetJoinTable) . "." . $propertyMapping->targetJoinColumn
                        ));
                } elseif ($propertyMapping->mappedBy && !empty($joinedClassMapping->properties[$propertyMapping->mappedBy])) {
                    //Relationship is inverted
                    $relatedPropertyMapping = $joinedClassMapping->properties[$propertyMapping->mappedBy];
                    $leftTable = $propertyMapping->tableName && isset($joinMappings[$alias]) ? $joinMappings[$alias]->tableOrAlias : $propertyMapping->tableName;// ? ($tableOrAlias ?: $propertyMapping->tableName) : '';
                    $leftColumn = $propertyMapping->columnName;
                    $targetTable = $alias ?: $relatedPropertyMapping->tableName;
                    $targetColumn = $relatedPropertyMapping->columnShortName;
                    if (!$leftTable || !$leftColumn) {
                        if ($relatedPropertyMapping->hasKnownDataType()) {
                            throw new MappingException(
                                sprintf(
                                    'There is a problem with the relationship mapping for %1$s, whose mappedBy attribute refers to %2$s. %2$s has a scalar data type, with no relationship mapping information. Please check that the correct value is being used for the mappedBy attribute, and that you have specified the mappedBy attribute only on the property that does NOT own the relationship (ie. does not map to the database column that contains the foreign key). The mappedBy attribute should *point to* the property that owns the relationship (is mapped to the database column that holds the foreign key). On a one-to-many relationship, the parent (one) will have a mappedBy attribute that points to the property representing the parent (ie. the foreign key) on the child (many).',
                                    $propertyMapping->className . '::' . $propertyMapping->propertyName,
                                    $relatedPropertyMapping->className . '::' . $relatedPropertyMapping->propertyName
                                )
                            );
                        }
                        $leftClassMapping = $this->objectMapper->getClassMapping(
                            $relatedPropertyMapping->dataType ? $relatedPropertyMapping->dataType : $propertyMapping->className
                        );
                        $leftTable = $leftClassMapping->tableName;
                        $leftColumn = $leftClassMapping->getPrimaryKeyPropertyMapping()->columnName;
                    }
                    if (!$targetTable || !$targetColumn) {
                        $targetTable = $alias ?: $joinedClassMapping->tableName;
                        $joinedPkPropertyMapping = $joinedClassMapping->getPrimaryKeyPropertyMapping();
                        $targetColumn = $joinedPkPropertyMapping ? $joinedPkPropertyMapping->columnName : null;
                    }
                    $joinType = ($relatedPropertyMapping->joinType ?: ($propertyMapping->joinType ?: 'LEFT'));
                    $targetJoinColumn = $relatedPropertyMapping->targetJoinColumn && $relatedPropertyMapping->targetJoinColumn != '**id**' ? $relatedPropertyMapping->targetJoinColumn : $leftColumn;
                    $joinSql = $this->delimitColumns(
                            $leftTable . "." . $targetJoinColumn
                        ) . " = " . $this->delimitColumns($targetTable . '.' . $targetColumn);
                    $joinSql = $relatedPropertyMapping->joinSql ?: $joinSql;
                    $sql .= " " . $joinType . " JOIN " . $this->delimitColumns(
                            $targetJoinTable
                        ) . " " . $this->delimitColumns($alias) . " ON " . $joinSql;
                }
            }
        }

        return $this->overrideQueryPart('joins', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * @param array $criteria
     * @return string The WHERE part of the SQL query.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getWhere(array $criteria = [])
    {
        $criteria = CB::create()->normalize($criteria); //As method is public, we have to normalize
        $this->params = [];
        $sql = ' WHERE 1 ';

        if ($this->latest) {
            $sql .= " AND $this->latestAlias." . $this->objectMapper->getIdColumn(false) . " IS NULL ";
        }

        foreach ($criteria as $criteriaExpression) {
            $sql .= $this->applyCriteria($criteriaExpression, 'AND');
        }

        $sql .= $this->customWhereClause ? " AND ($this->customWhereClause) " : "";

        return $this->overrideQueryPart('where', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * @return string The SQL string for the GROUP BY clause, if applicable.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getGroupBy(array $criteria = [], $ignoreCount = false)
    {
        //This function can be overridden, but baseGroupBy cannot be - we need to know whether the value is ours or not.
        return $this->baseGroupBy($criteria, $ignoreCount);
    }

    /**
     * @param array $criteria
     * @return string The SQL string for the HAVING clause, if applicable (used where the criteria involves an
     * aggregate function, either directly in the criteria itself, or by comparing against a property that uses one.
     * @throws Exception\CriteriaException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getHaving(array $criteria = [])
    {
        $sql = '';
        $criteria = CB::create()->normalize($criteria); //As method is public, we have to normalize

        $having = '';
        $joinMappings = $this->objectMapper->getJoinMappings($this->entityClassName, $criteria);
        foreach ($criteria as $criteriaExpression) {
            $having .= $this->applyCriteria($criteriaExpression, 'AND', true, $joinMappings);
        }
        $sql = $having ? ' HAVING 1 ' . $having : '';

        return $this->overrideQueryPart('having', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * @param array $criteria
     * @return string The SQL string for the ORDER BY clause.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getOrderBy(array $criteria = [])
    {
        $sql = '';

        if (!$this->count) {
            if (!isset($this->orderBy) && !empty($this->objectMapper->getIdColumn(false))) {
                $sql = " ORDER BY $this->mainTable." . $this->objectMapper->getIdColumn(false) . " ASC ";
            } elseif (!empty($this->orderBy)) {
                $sql = " ORDER BY ";
                foreach ($this->orderBy as $property => $direction) {
                    if (is_int($property) && !in_array(
                            strtoupper($direction),
                            ['ASC', 'DESC']
                        )) { //Indexed array, not associative
                        $property = $direction;
                        $direction = '';
                    }
                    $propertyMapping = $this->objectMapper->getPropertyMapping($this->entityClassName, $property);
                    if ($propertyMapping) {
                        if ($propertyMapping->aggregateFunction) {
                            $classMapping = $this->objectMapper->getClassMapping($this->entityClassName);
                            $sql .= $this->objectMapper->constructAggregateFunction($classMapping, $propertyMapping);
                        } else {
                            $sql .= $propertyMapping->getFullColumnName();
                        }
                        $sql .= " " . (in_array(strtoupper($direction), ['ASC', 'DESC']) ? strtoupper(
                                $direction
                            ) : 'ASC') . ',';
                    }
                }
                $sql = rtrim($sql, ',');
            }
        }

        return $this->overrideQueryPart('orderby', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * @param array $criteria
     * @return string The SQL string for the LIMIT clause (if using pagination).
     */
    public function getLimit(array $criteria = [])
    {
        $sql = '';

        if (!$this->multiple) {
            $sql = ' LIMIT 1 ';
        } elseif (!$this->count && !empty($this->pagination)) {
            $sql = ' LIMIT ' . $this->pagination->getRecordsPerPage() . ' ';
            if ($this->pagination->getOffset()) {
                $sql .= ' OFFSET ' . $this->pagination->getOffset() . ' ';
            }
        }

        return $this->overrideQueryPart('limit', $sql, $criteria, $this->getQueryParams());
    }

    /**
     * Build an SQL expression based on an Objectiphy CriteriaExpression object or Doctrine-compatible criteria array.
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
                $parentClassMapping = null;
                $propertyMapping = $this->objectMapper->getPropertyMapping(
                    $this->entityClassName,
                    $expression->propertyName,
                    true,
                    $parentClassMapping
                );
                $joinMappingAlias = $this->objectMapper->joinMapper->getJoinMappingKeyForPropertyPath(
                    $this->entityClassName,
                    $expression->propertyName,
                    $joinMappings,
                    $parentClassMapping ? $parentClassMapping->tableName : ''
                );
                if ($propertyMapping) {
                    $applyCriteria = ($having && ($expression->aggregateFunction || $propertyMapping->aggregateFunction))
                        || (!$having && !$expression->aggregateFunction && !$propertyMapping->aggregateFunction);
                    if ($applyCriteria) { //Criteria involving aggregate functions use HAVING
                        if ($propertyMapping->aggregateFunction) { //Find the correct alias for the collection
                            $aggregateCollectionClass = $parentClassMapping->properties[$propertyMapping->aggregateCollection]->dataType;
                            $aggregateCollectionPropertyPathParts = explode('.', $expression->propertyName);
                            array_pop($aggregateCollectionPropertyPathParts);
                            $aggregateCollectionPropertyPath = implode(
                                '.',
                                array_merge(
                                    $aggregateCollectionPropertyPathParts,
                                    explode(
                                        '.',
                                        $propertyMapping->aggregateCollection
                                    )
                                )
                            );
                            $joinMappingAlias = $having ? $this->objectMapper->joinMapper->getJoinMappingKeyForPropertyPath(
                                $this->entityClassName,
                                $aggregateCollectionPropertyPath,
                                $joinMappings
                            ) : '';
                            $aggregatePropertyMapping = $this->objectMapper->getPropertyMapping(
                                $aggregateCollectionClass,
                                $propertyMapping->aggregateProperty
                            );
                            $columnName = $aggregatePropertyMapping->getFullColumnName(true, $joinMappingAlias);
                            $columnName = $propertyMapping->aggregateFunction . '(' . $columnName . ')';
                        } else {
                            $tableAlias = $joinMappingAlias ?: (!$propertyMapping->tableName && $parentClassMapping ? $parentClassMapping->tableName : '');
                            $columnPrefix = $this->objectMapper->getColumnPrefixForPropertyPath(
                                $expression->propertyName
                            );
                            $columnName = $propertyMapping->getFullColumnName(
                                true,
                                $tableAlias,
                                false,
                                false,
                                $columnPrefix
                            ); //Embedded objects do not have a table name - use parent
                            if ($expression->aggregateFunction) {
                                $columnName = $expression->aggregateFunction . '(' . $columnName . ')';
                            }
                        }
                        $dateFormat = $propertyMapping->format && (in_array(
                                $propertyMapping->dataType,
                                ['datetimestring', 'datestring']
                            ) || strpos(
                                strtolower($propertyMapping->propertyName),
                                'date'
                            ) !== false) ? $propertyMapping->format : '';
                        $sql = " $joiner (" . $this->applyCriteriaValue(
                                $columnName,
                                $operator,
                                $value,
                                $value2,
                                $dateFormat
                            );
                    }
                    foreach ($expression->andExpressions as $andExpression) {
                        $sql .= $this->applyCriteria($andExpression, 'AND', $having, $joinMappings);
                    }
                    foreach ($expression->orExpressions as $orExpression) {
                        $sql .= $this->applyCriteria($orExpression, 'OR', $having, $joinMappings);
                    }
                    if ($applyCriteria) {
                        $sql .= ")";
                    }
                }
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
        if (substr($value, 0, 1) == '`' && substr($value, -1) == '`') {
            $columnName = $this->objectMapper->getColumnForPropertyPath(str_replace('`', '', $value));
            if ($columnName) {
                $value = $columnName;
                return true;
            }
        }

        return false;
    }

    /**
     * Convert "database.table.column" to "`database`.`table`.`column`".
     * @param string $tableColumnString Database/Table/Column separated by a dot.
     * @return string Backtick separated string equivalent.
     */
    private function delimitColumns($tableColumnString)
    {
        $delimited = '';
        if ($tableColumnString) {
            $delimited = "`" . implode("`.`", explode('.', str_replace("`", "", $tableColumnString))) . "`";
        }

        return $delimited;
    }

    /**
     * @param array $criteria
     * @param bool $ignoreCount
     * @return string SQL string for the GROUP BY clause, base implementation (cannot be overridden).
     * @throws MappingException
     * @throws \ReflectionException
     */
    private function baseGroupBy(array $criteria = [], $ignoreCount = false)
    {
        $sql = '';

        if ($ignoreCount || !$this->countWithoutGroups) {
            $sql .= " GROUP BY ";
            $groups = [];
            $aggregateGroupByColumns = $this->objectMapper->getAggregateGroupByColumns();
            if ($aggregateGroupByColumns) {
                $groups = array_merge($groups, $aggregateGroupByColumns);
            }
            if ($this->latest) {
                //Getting latest record
                $groups[] = $this->delimitColumns($this->mainTable . '.' . $this->objectMapper->getCommonShortColumn());
            } elseif ($this->groupByPrimaryKey) {
                $groups[] = $this->delimitColumns($this->objectMapper->getIdColumn());
            }
            if (!empty(array_filter($groups))) {
                $sql .= ' ' . implode(', ', array_unique($groups)) . ' ';
            } else {
                $sql = '';
            }
        }

        return $this->overrideQueryPart('groupby', $sql, $criteria, $this->getQueryParams());
    }

    private function overrideQueryPart($part, $generatedQuery, $criteria, $params)
    {
        $override = !empty($this->queryOverrides[strtolower($part)]) ? $this->queryOverrides[strtolower(
            $part
        )] : $generatedQuery;
        if (is_callable($override)) {
            $override = call_user_func_array($override, [$generatedQuery, $criteria, $params]);
        } elseif (!is_string($override)) { //We don't know what the heck this is - just use our generated query
            $override = $generatedQuery;
        }

        return $override;
    }
}
