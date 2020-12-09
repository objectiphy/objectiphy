<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Query\InsertQuery;
use Objectiphy\Objectiphy\Query\Query;
use Objectiphy\Objectiphy\Query\UpdateQuery;

/**
 * Provider of SQL for update queries on MySQL
 * @package Objectiphy\Objectiphy\Database\MySql
 */
class SqlUpdaterMySql extends AbstractSqlProvider implements SqlUpdaterInterface
{
    protected $objectNames;
    protected $persistenceNames;
    private SaveOptions $options;
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

    public function setSaveOptions(SaveOptions $options): void
    {
        $this->options = $options;
        $this->setMappingCollection($options->mappingCollection);
        $this->joinProvider->setMappingCollection($options->mappingCollection);
        $this->whereProvider->setMappingCollection($options->mappingCollection);
    }

    /**
     * Get the SQL necessary to perform the insert.
     * @param InsertQuery $query
     * @return string A query to execute for inserting the record.
     */
    public function getInsertSql(InsertQuery $query): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Builder has not been initialised. There is no mapping information!');
        }

        $this->params = [];
        $this->prepareReplacements($query, $this->options->mappingCollection, '`');

        $sql = 'INSERT INTO ';
        $sql .= $this->replaceNames($query->getInsert());
        $sql .= ' SET ';
        $sqlAssignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $sqlAssignments[] = $assignment->toString($this->params);
        }
        $sql .= $this->replaceNames(implode(', ', $sqlAssignments));
        array_walk($this->params, function(&$value) {
            $this->dataTypeHandler->toPersistenceValue($value);
        });

        return $sql;
    }

    /**
     * Get the SQL statements necessary to update the given row record.
     * @param string $className Name of the parent entity class for the record being updated (used to get the
     * primary key column).
     * @param array $row Row of data to update.
     * @param array $pkValues Value of primary key for record to update.
     * @return array An array of SQL queries to execute for updating the entity.
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function getUpdateSql(UpdateQuery $query, bool $replaceExisting = false, array $parents = []): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Builder has not been initialised. There is no mapping information!');
        }

        $this->params = [];
        $this->prepareReplacements($query, $this->options->mappingCollection, '`', $parents);

        $sql = 'UPDATE ';
        $sql .= $this->replaceNames($query->getUpdate());
        $this->joinProvider->setQueryParams($this->params);
        $sql .= $this->joinProvider->getJoins($query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->joinProvider->getQueryParams());
        $sql .= ' SET ';
        $sqlAssignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $sqlAssignments[] = $assignment->toString($this->params);
        }
        $sql .= $this->replaceNames(implode(', ', $sqlAssignments));
        $this->whereProvider->setQueryParams($this->params);
        $sql .= $this->whereProvider->getWhere($query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->whereProvider->getQueryParams());

        return $sql;
    }

    public function getReplaceSql(Table $table, array $row, array $pkValues): array
    {
        // TODO: Implement getReplaceSql() method.
    }

    /**
     * Build arrays of strings to replace and what to replace them with.
     * @param string $delimiter
     */
    protected function prepareReplacements(
        Query $query,
        MappingCollection $mappingCollection,
        string $delimiter = '`',
        array $parents = []
    ): void {
        $this->sql = '';
        $this->objectNames = [];
        $this->persistenceNames = [];
        $this->aliases = [];

        $propertiesUsed = $query->getPropertyPaths();
        $parentPath = $parents ? implode('.', $parents) . '.' : '';
        foreach ($propertiesUsed as $propertyPath) {
            $property = $mappingCollection->getPropertyMapping($parentPath . $propertyPath);
            if (!$property) {
                //Need to account for custom join alias...
                //Just need to keep alias the same and change property name to short column name
                if ($this->prepareCustomJoinAliasReplacements($propertyPath, $query, $mappingCollection)) {
                    continue;
                } else {
                    $message = sprintf('Property mapping not found for: %1$s on class %2$s.', $parentPath . $propertyPath, $mappingCollection->getEntityClassName());
                    throw new QueryException($message);
                }
            }
            $this->objectNames[] = '`' . $property->getPropertyPath() . '`';
            $this->persistenceNames[] = $this->delimit($property->getFullColumnName(), $delimiter);
            if (strlen($parentPath) > 0) {
                $this->objectNames[] = '`' . substr($property->getPropertyPath(), strlen($parentPath)) . '`';
                $tableColumnString = $property->getShortColumnName(false);
                $this->persistenceNames[] = $this->delimit($tableColumnString, $delimiter);
            }
        }
        $tables = $mappingCollection->getTables();
        foreach ($tables as $class => $table) {
            $this->objectNames[] = $class;
            $this->persistenceNames[] = $this->delimit(str_replace($delimiter, '', $table->name)) ;
        }
    }
    
    private function prepareCustomJoinAliasReplacements(
        string $propertyPath, 
        Query $query, 
        MappingCollection $mappingCollection
    ): bool {
        $aliasDelimiterPos = strpos($propertyPath, '.');
        if ($aliasDelimiterPos !== false) {
            $alias = substr($propertyPath, 0, $aliasDelimiterPos);
            $aliasClass = $query->getClassForAlias($alias);
            $propertyName = substr($propertyPath, $aliasDelimiterPos + 1);
            $propertyMapping = $mappingCollection->getPropertyExample($aliasClass, $propertyName);
            if ($propertyMapping) {
                $this->objectNames[] = '`' . $alias . '.' . $propertyMapping->propertyName . '`';
                $aliasAndColumn = $alias . '.' . $propertyMapping->getShortColumnName(false);
                $this->persistenceNames[] = $this->delimit($aliasAndColumn);

                return true;
            }
        }

        return false;
    }

    protected function replaceNames(string $subject): string
    {
        if (!isset($this->objectNames)) {
            throw new ObjectiphyException('Please call prepareReplacements method before attempting to replace.');
        }

        return str_replace($this->objectNames, $this->persistenceNames, $subject);
    }
}
