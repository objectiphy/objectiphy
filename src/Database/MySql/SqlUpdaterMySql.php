<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\InsertQueryInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

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
     * @param bool $replaceExisting Whether to update existing record if it already exists.
     * @param bool $parseDelimiters Whether or not to look for delimiters in values (if false, all values are literal).
     * @return string A query to execute for inserting the record.
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function getInsertSql(
        InsertQueryInterface $query,
        bool $replaceExisting = false,
        bool $parseDelimiters = true
    ): string {
        if (!isset($this->options->mappingCollection)) {
            throw new ObjectiphyException('SQL Builder has not been initialised. There is no mapping information!');
        }

        $this->stringReplacer->prepareReplacements($query, $this->options->mappingCollection);
        $sql = "/* insert */\nINSERT INTO \n";
        $sql .= $this->stringReplacer->replaceNames($query->getInsert());
        $columns = $this->extractColumns($query->getAssignments()[0], $query);
        $sql .= " \n(" . implode(',', $columns) . ") \n/* insert values */\nVALUES \n";
        foreach ($query->getAssignments() as $index => $assignments) {
            $sql .= $index > 0 ? ", \n" : '';
            $this->stringReplacer->parseDelimiters = $parseDelimiters;
            $sql .= "(" . implode(',', $this->extractValues($assignments, $query)) . ")";
            $this->stringReplacer->parseDelimiters = true; //Ready for the next call
        }
        if ($replaceExisting) {
            //Deprecated in MySQL 8, but the alternative syntax is not supported in earlier versions...
            $sql .= " ON DUPLICATE KEY UPDATE \n";
            $updates = [];
            foreach ($columns as $column) {
                $updates[] = "$column = VALUES($column)";
            }
            $sql .= implode(', ', $updates);
        }

        return $this->stringReplacer->replaceNames($sql);
    }

    /**
     * Get the SQL statements necessary to update the given row record.
     * @param UpdateQueryInterface $query
     * @param bool $replaceExisting
     * @param bool $parseDelimiters Whether or not to look for delimiters in values (if false, all values are literal).
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function getUpdateSql(
        UpdateQueryInterface $query,
        bool $replaceExisting = false,
        bool $parseDelimiters = true
    ): string {
        $this->stringReplacer->prepareReplacements($query, $this->options->mappingCollection);
        $sql = "/* update */\nUPDATE \n";
        $sql .= $this->stringReplacer->replaceNames($query->getUpdate());
        $sql .= $this->joinProvider->getJoins($query);
        $sql .= " /* update set */\n SET \n";
        $sql .= $this->constructAssignmentSql($query, $parseDelimiters);
        $sql = trim($sql) . $this->whereProvider->getWhere($query, $this->options->mappingCollection);
        $sql .= $this->whereProvider->getHaving($query, $this->options->mappingCollection);

        return $this->stringReplacer->replaceNames($sql);
    }

    /**
     * @param QueryInterface $query
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function constructAssignmentSql(UpdateQueryInterface $query, bool $parseDelimiters = true)
    {
        $assignments = [];
        foreach ($query->getAssignments() as $assignment) {
            $assignmentString = $this->stringReplacer->getPersistenceValueForField($query, $assignment->getPropertyPath(), $this->options->mappingCollection);
            $assignmentString .= ' = ';
            $propertyMapping = $this->options->mappingCollection->getPropertyMapping($assignment->getPropertyPath());
            $dataType = $propertyMapping ? $propertyMapping->getDataType() : '';
            $format = $propertyMapping ? $propertyMapping->column->format : '';
            $this->stringReplacer->parseDelimiters = $parseDelimiters;
            $assignmentString .= $this->stringReplacer->getPersistenceValueForField($query, $assignment->getValue(), $this->options->mappingCollection, $dataType, $format, $parseDelimiters);
            $this->stringReplacer->parseDelimiters = true; //Ready for the next call
            $assignments[] = $assignmentString;
        }

        return "    " . $this->stringReplacer->replaceNames(implode(",\n    ", $assignments)) . "\n";
    }

    private function extractColumns(array $assignments, InsertQueryInterface $query)
    {
        $columns = [];
        foreach ($assignments as $assignment) {
            $columns[] = $this->stringReplacer->getPersistenceValueForField(
                $query,
                $assignment->getPropertyPath(),
                $this->options->mappingCollection
            );
        }

        return $columns;
    }

    private function extractValues(array $assignments, InsertQueryInterface $query)
    {
        $values = [];
        foreach ($assignments as $assignment) {
            $propertyMapping = $this->options->mappingCollection->getPropertyMapping($assignment->getPropertyPath());
            $dataType = $propertyMapping ? $propertyMapping->getDataType() : '';
            $format = $propertyMapping ? $propertyMapping->column->format : '';
            $assignmentString = $this->stringReplacer->getPersistenceValueForField(
                $query,
                $assignment->getValue(),
                $this->options->mappingCollection,
                $dataType,
                $format
            );
            $values[] = $assignmentString;
        }

        return $values;
    }
}
