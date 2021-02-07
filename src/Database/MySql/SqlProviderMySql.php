<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Common functionality for select and delete
 */
class SqlProviderMySql extends AbstractSqlProvider
{
    protected QueryInterface $query;
    protected JoinProviderMySql $joinProvider;
    protected WhereProviderMySql $whereProvider;

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
     * For a custom join, if you use a column (not a property), you must delimit yourself
     * If you use an expression or function, you must delimit values ('), properties (%), and columns (`) yourself
     * So if no delimiter, it is either a property, or a literal value
     * Detect a property by seeing if the property path exists
     * Otherwise fall back to a value - and wrap in quotes (works for ints/dates as well as strings)
     * @param string $fieldValue
     */
    protected function getSqlForField(string $fieldValue)
    {
        if (substr_count($fieldValue, '%') >= 2 || substr_count($fieldValue, '`') >= 2) {
            //Delimited - use as is

        } elseif (substr_count($fieldValue, "'") >= 2) {
            //Check how many are unescaped - if 2 or more are unescaped, treat it as already delimited, otherwise, value

        } else {
            //Check if is is a property path

            //If not, treat it as a value

        }

        /*
        substr($propertyPath, 0, strlen($this->currentJoinAlias) + 1) == $this->currentJoinAlias . '.') {
        $propertyPath = substr($propertyPath, strpos($propertyPath, '.') + 1);

        //Split this out and call for both property and value to get any aliased columns
        $mappingCollection = $this->objectMapper->getMappingCollectionForClass($this->currentJoinTargetClass);
        $propertyMapping = $mappingCollection->getPropertyMapping($propertyPath);
        $column = $this->currentJoinAlias . '.' . $propertyMapping->getShortColumnName(false);
         */
    }

    /**
     * Build arrays of strings to replace and what to replace them with.
     * @param MappingCollection $mappingCollection
     * @param string $objectDelimiter Delimiter for property paths
     * @param string $databaseDelimiter Delimiter for tables and columns
     */
    protected function prepareReplacements(
        MappingCollection $mappingCollection,
        string $objectDelimiter = '%',
        $databaseDelimiter = '`'
    ): void {
        $this->sql = '';
        $this->objectNames = [];
        $this->persistenceNames = [];
        $this->aliases = [];

        $propertiesUsed = $this->query->getPropertyPaths();
        foreach ($propertiesUsed as $propertyPath) {
            $property = $mappingCollection->getPropertyMapping($propertyPath);
            if (!$property) {
                //Just use the value as a literal string
                $this->objectNames[] = '%' . $propertyPath . '%';
                $this->persistenceNames[] = $propertyPath;
                $this->aliases[] = '';
                continue;
            }
            $this->objectNames[] = '%' . $propertyPath . '%';
            if (strpos($propertyPath, '.') === false && strpos($this->query->getClassName(), '`') !== false) {
                //We are fetching from an explicitly specified table name - use it for any root properties
                $tableColumnString = $property->getFullColumnName($this->query->getClassName());
            } else {
                $tableColumnString = $property->getFullColumnName();
            }
            $this->persistenceNames[] = $this->delimit($tableColumnString, $databaseDelimiter);
            $this->aliases[] = $this->delimit($tableColumnString, $databaseDelimiter)
                . ' AS ' . $this->delimit($property->getAlias(), $databaseDelimiter);
        }
        $tables = $mappingCollection->getTables();
        foreach ($tables as $class => $table) {
            $this->objectNames[] = $class;
            $this->persistenceNames[] = $this->delimit(str_replace($databaseDelimiter, '', $table->name), $databaseDelimiter);
        }
    }
}
