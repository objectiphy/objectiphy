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
     * @param string $delimiter
     * @param string $altDelimiter
     * @throws QueryException
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
}
