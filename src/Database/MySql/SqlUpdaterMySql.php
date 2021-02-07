<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\InsertQueryInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Orm\ObjectMapper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for update queries on MySQL
 */
class SqlUpdaterMySql extends AbstractSqlProvider implements SqlUpdaterInterface
{
    protected array $objectNames;
    protected array $persistenceNames;
    private SaveOptions $options;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;

    public function __construct(
        ObjectMapper $objectMapper,
        DataTypeHandlerInterface $dataTypeHandler,
        JoinProviderMySql $joinProvider,
        WhereProviderMySql $whereProvider
    ) {
        parent::__construct($objectMapper, $dataTypeHandler);
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
     * @param InsertQueryInterface $query
     * @param bool $replace Whether to update existing record if it already exists.
     * @return string A query to execute for inserting the record.
     * @throws ObjectiphyException
     * @throws QueryException
     */
    public function getInsertSql(InsertQueryInterface $query, bool $replace = false): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Builder has not been initialised. There is no mapping information!');
        }

        $this->params = [];
        $this->prepareReplacements($query, $this->options->mappingCollection);

        $sql = "INSERT INTO \n";
        $sql .= $this->replaceNames($query->getInsert());
        $sql .= "SET \n";
        $sqlAssignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $sqlAssignments[] = $assignment->toString($this->params);
        }
        $assignments = $this->replaceNames(implode(",    \n", $sqlAssignments)) . "\n";
        $sql .= "    " . $assignments;
        if ($replace) {
            $sql .= 'ON DUPLICATE KEY UPDATE ' . $assignments . "\n";
        }

        array_walk($this->params, function(&$value) {
            $this->dataTypeHandler->toPersistenceValue($value);
        });

        return $sql;
    }

    /**
     * Get the SQL statements necessary to update the given row record.
     * @param UpdateQueryInterface $query
     * @param bool $replaceExisting
     * @param array $parents
     * @return string
     * @throws ObjectiphyException
     */
    public function getUpdateSql(UpdateQueryInterface $query, bool $replaceExisting = false, array $parents = []): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Updater has not been initialised. There is no mapping information!');
        }

        $this->params = [];
        $this->prepareReplacements($query, $this->options->mappingCollection, $parents);

        $sql = "UPDATE \n";
        $sql .= $this->replaceNames($query->getUpdate());
        $this->joinProvider->setQueryParams($this->params);
        $sql .= $this->joinProvider->getJoins($query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->joinProvider->getQueryParams());
        $sql .= " SET \n";
        $sqlAssignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $sqlAssignments[] = $assignment->toString($this->params);
        }
        $sql .= "    " . $this->replaceNames(implode(", \n", $sqlAssignments)) . "\n";
        $this->whereProvider->setQueryParams($this->params);
        $sql .= $this->whereProvider->getWhere($query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->whereProvider->getQueryParams());

        return $sql;
    }

    /**
     * Build arrays of strings to replace and what to replace them with.
     * @param QueryInterface $query
     * @param MappingCollection $mappingCollection
     * @param string $objectDelimiter Delimiter for property paths
     * @param string $databaseDelimiter Delimiter for tables and columns
     * @param array $parents
     * @throws QueryException
     */
    protected function prepareReplacements(
        QueryInterface $query,
        MappingCollection $mappingCollection,
        array $parents = [],
        string $objectDelimiter = '%',
        string $databaseDelimiter = '`'
    ): void {
        $this->sql = '';
        $this->objectNames = [];
        $this->persistenceNames = [];

        $propertiesUsed = $query->getPropertyPaths();
        $parentPath = $parents ? implode('.', $parents) . '.' : '';
        foreach ($propertiesUsed as $propertyPath) {
            $property = $mappingCollection->getPropertyMapping($parentPath . $propertyPath);
            if (!$property) {
                //Just need to keep alias the same and change property name to short column name
                if ($this->prepareCustomJoinAliasReplacements($propertyPath, $query, $mappingCollection)) {
                    continue;
                } else {
                    //Just use the value as a literal string
                    $this->objectNames[] = '%' . $propertyPath . '%';
                    $this->persistenceNames[] = $propertyPath;
                    continue;
//                    $message = sprintf('Property mapping not found for: %1$s on class %2$s.', $parentPath . $propertyPath, $mappingCollection->getEntityClassName());
//                    throw new QueryException($message);
                }
            }
            $this->objectNames[] = $objectDelimiter . $property->getPropertyPath() . $objectDelimiter;
            $this->persistenceNames[] = $this->delimit($property->getFullColumnName(), $databaseDelimiter);
            if (strlen($parentPath) > 0) {
                $this->objectNames[] = $objectDelimiter . substr($property->getPropertyPath(), strlen($parentPath)) . $objectDelimiter;
                $tableColumnString = $property->getShortColumnName(false);
                $this->persistenceNames[] = $this->delimit($tableColumnString, $databaseDelimiter);
            }
        }
        $tables = $mappingCollection->getTables();
        foreach ($tables as $class => $table) {
            $this->objectNames[] = $class;
            $this->persistenceNames[] = $this->delimit(str_replace($databaseDelimiter, '', $table->name), $databaseDelimiter) ;
        }
    }

    private function prepareCustomJoinAliasReplacements(
        string $propertyPath,
        QueryInterface $query,
        MappingCollection $mappingCollection,
        string $objectDelimiter = '%',
        string $databaseDelimiter = '`'
    ): bool {
        $aliasDelimiterPos = strpos($propertyPath, '.');
        if ($aliasDelimiterPos !== false) {
            $alias = substr($propertyPath, 0, $aliasDelimiterPos);
            $aliasClass = $query->getClassForAlias($alias);
            $propertyName = substr($propertyPath, $aliasDelimiterPos + 1);
            $propertyMapping = $mappingCollection->getPropertyExample($aliasClass, $propertyName);
            if ($propertyMapping) {
                $this->objectNames[] = $objectDelimiter . $alias . '.' . $propertyMapping->propertyName . $objectDelimiter;
                $aliasAndColumn = $alias . '.' . $propertyMapping->getShortColumnName(false);
                $this->persistenceNames[] = $this->delimit($aliasAndColumn, $databaseDelimiter);

                return true;
            }
        }

        return false;
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
