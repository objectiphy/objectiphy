<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\DeleteOptions;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlDeleterInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for select queries on MySQL
 */
class SqlDeleterMySql implements SqlDeleterInterface
{
    private DeleteOptions $options;
    private SqlStringReplacer $stringReplacer;
    private JoinProviderMySql $joinProvider;
    private WhereProviderMySql $whereProvider;

    public function __construct(
        SqlStringReplacer $stringReplacer,
        JoinProviderMySql $joinProvider,
        WhereProviderMySql $whereProvider
    ) {
        $this->stringReplacer = $stringReplacer;
        $this->joinProvider = $joinProvider;
        $this->whereProvider = $whereProvider;
    }

    public function setDeleteOptions(DeleteOptions $options): void
    {
        $this->options = $options;
    }

    /**
     * Get the SQL query necessary to delete the records specified by the given query.
     * @param DeleteQueryInterface $query
     * @return string The SQL query to execute.
     * @throws \Exception
     */
    public function getDeleteSql(DeleteQueryInterface $query): string
    {
        $this->stringReplacer->prepareReplacements($query, $this->options->mappingCollection);
        $sql = "/* delete */\nDELETE FROM \n" . $this->stringReplacer->replaceNames((string) $query->getDelete()) . "\n";
        $sql .= $this->joinProvider->getJoins($query);
        $sql = trim($sql) . $this->whereProvider->getWhere($query, $this->options->mappingCollection);
        $sql .= $this->whereProvider->getHaving($query, $this->options->mappingCollection);

        return $sql;
    }
}
