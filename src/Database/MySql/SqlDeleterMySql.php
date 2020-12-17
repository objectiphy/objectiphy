<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
use Objectiphy\Objectiphy\Database\AbstractSqlProvider;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;

/**
 * Provider of SQL for select queries on MySQL
 * @package Objectiphy\Objectiphy\Database\MySql
 */
class SqlDeleterMySql extends AbstractSqlProvider implements SqlDeleterInterface
{
    protected array $objectNames = [];
    protected array $persistenceNames = [];
    protected array $aliases = [];

    private DeleteOptions $options;
    private DeleteQueryInterface $query;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;

    public function __construct(
        JoinProviderMySql $joinProvider,
        WhereProviderMySql $whereProvider
    ) {
        $this->joinProvider = $joinProvider;
        $this->whereProvider = $whereProvider;
    }

    public function setDeleteOptions(DeleteOptions $options): void
    {
        $this->options = $options;
        $this->setMappingCollection($options->mappingCollection);
        $this->joinProvider->setMappingCollection($options->mappingCollection);
        $this->whereProvider->setMappingCollection($options->mappingCollection);
    }

    /**
     * Get the SQL query necessary to delete the records specified by the given query.
     * @return string The SQL query to execute.
     */
    public function getDeleteSql(DeleteQueryInterface $query): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Deleter has not been initialised. There is no mapping information!');
        }

        $this->query = $query;
        $this->params = [];
        $this->prepareReplacements($this->options->mappingCollection, '`', '|');

        $sql = 'DELETE FROM ' . $this->replaceNames((string) $query->getDelete());
        $this->joinProvider->setQueryParams($this->params);
        $sql .= $this->joinProvider->getJoins($this->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->joinProvider->getQueryParams());
        $this->whereProvider->setQueryParams($this->params);
        $sql .= $this->whereProvider->getWhere($this->query, $this->objectNames, $this->persistenceNames);
        $this->setQueryParams($this->whereProvider->getQueryParams());

        $sql = str_replace('|', '`', $sql); //Revert to backticks now the replacements are done.

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
