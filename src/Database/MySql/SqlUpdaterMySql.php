<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\InsertQueryInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Exception\QueryException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Provider of SQL for update queries on MySQL
 */
class SqlUpdaterMySql implements SqlUpdaterInterface
{
    private SaveOptions $options;
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

    public function setSaveOptions(SaveOptions $options): void
    {
        $this->options = $options;
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

        $this->stringReplacer->prepareReplacements($query);

        $sql = "INSERT INTO \n";
        $sql .= $this->stringReplacer->replaceNames($query->getInsert());
        $sql .= "SET \n";
        $sqlAssignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $sqlAssignments[] = $assignment->toString($query->getParams());
        }
        $assignments = $this->replaceNames(implode(",    \n", $sqlAssignments)) . "\n";
        $sql .= "    " . $assignments;
        if ($replace) {
            $sql .= 'ON DUPLICATE KEY UPDATE ' . $assignments . "\n";
        }

        array_walk($query->getParams(), function(&$value) use ($query) {
            $value = $this->stringReplacer->getPersistenceValueForField($query, $value);
        });

        return $this->stringReplacer->replaceNames($sql);
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
        $this->stringReplacer->prepareReplacements($query);
        $sql = "UPDATE \n";
        $sql .= $this->stringReplacer->replaceNames($query->getUpdate());
        $sql .= $this->joinProvider->getJoins($query);
        $sql .= " SET \n";
        $assignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $assignmentString = $this->stringReplacer->getPersistenceValueForField($query, $assignment->getPropertyPath());
            $assignmentString .= ' = ';
            $assignmentString .= $this->stringReplacer->getPersistenceValueForField($query, $assignment->getValue());
            $assignments[] = $assignmentString;
        }
        $sql .= "    " . $this->stringReplacer->replaceNames(implode(",    \n", $assignments)) . "\n";
        $sql .= $this->whereProvider->getWhere($query);

        return $this->stringReplacer->replaceNames($sql);
    }
}
