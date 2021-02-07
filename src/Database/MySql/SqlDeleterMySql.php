<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for select queries on MySQL
 */
class SqlDeleterMySql extends SqlProviderMySql implements SqlDeleterInterface
{
    private DeleteOptions $options;

    public function setDeleteOptions(DeleteOptions $options): void
    {
        $this->options = $options;
        $this->setMappingCollection($options->mappingCollection);
        $this->joinProvider->setMappingCollection($options->mappingCollection);
        $this->whereProvider->setMappingCollection($options->mappingCollection);
    }

    /**
     * Get the SQL query necessary to delete the records specified by the given query.
     * @param DeleteQueryInterface $query
     * @return string The SQL query to execute.
     * @throws \Exception
     */
    public function getDeleteSql(DeleteQueryInterface $query): string
    {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Deleter has not been initialised. There is no mapping information!');
        }

        $this->params = [];
        $this->query = $query;
        $this->prepareReplacements($this->options->mappingCollection);

        $sql = "DELETE FROM \n" . $this->replaceNames((string) $query->getDelete()) . "\n";
        $sql .= $this->addJoins();

        return $sql;
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
}
