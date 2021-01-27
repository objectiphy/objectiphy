<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\QueryException;
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
    protected array $objectNames = [];
    protected array $persistenceNames = [];
    protected array $aliases = [];

    public function __construct(
        DataTypeHandlerInterface $dataTypeHandler,
        JoinProviderMySql $joinProvider,
        WhereProviderMySql $whereProvider
    ) {
        parent::__construct($dataTypeHandler);
        $this->joinProvider = $joinProvider;
        $this->whereProvider = $whereProvider;
    }

    protected function addJoins(): string
    {
        $this->joinProvider->setQueryParams($this->params);
        $sql = $this->joinProvider->getJoins($this->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->joinProvider->getQueryParams());
        $this->whereProvider->setQueryParams($this->params);
        $sql .= $this->whereProvider->getWhere($this->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->whereProvider->getQueryParams());

        return $sql;
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
