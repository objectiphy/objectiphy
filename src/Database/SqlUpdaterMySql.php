<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Config\SaveOptions;
use Objectiphy\Objectiphy\Contract\SqlUpdaterInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\Table;

class SqlUpdaterMySql extends AbstractSqlProvider implements SqlUpdaterInterface
{
    private SaveOptions $options;

    public function setSaveOptions(SaveOptions $options): void
    {
        $this->options = $options;
    }

    public function setQueryParams(array $params = []): void
    {
        $this->params = $params;
    }

    /**
     * Return the parameter values to bind to the SQL statement. Where more than one SQL statement is involved, the
     * index identifies which one we are dealing with.
     * @param int|null $index Index of the SQL query.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams(?int $index = null): array
    {
        $params = [];
        if ($index !== null && ($index != 0 || isset($this->params[$index]))) {
            $params = $this->params[$index] ?: [];
        } else {
            $params = !empty($this->params) ? $this->params : [];
        }

        return $params;
    }

    /**
     * Get the SQL statements necessary to insert the given row.
     * @param Table $table Table to insert into.
     * @param array $row The row to insert.
     * @return array An array of SQL queries to execute for inserting this record (base implementation will always
     * return a single SQL statement, but extended classes might need to execute multiple queries).
     */
    public function getInsertSql(Table $table, array $row): array
    {
        $this->params = [];

        if (!empty($row['table']) && !empty($row['data'])) {
            $sql = "INSERT INTO " . $this->delimit($row['table']) . " SET ";
            $assignments = '';
            foreach ($row['data'] as $column => $value) {
                $value = $value instanceof ObjectReferenceInterface ? $value->getPrimaryKeyValue() : $value;
                $paramName = 'param_' . strval(count($this->params));
                $assignments .= $this->delimit($column) . " = :" . $paramName . ',';
                $this->params[$paramName] = $value;
            }
            $assignments = rtrim($assignments, ",");
            $sql .= $assignments;
            if ($replace || !empty($row['isScalarJoin'])) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . $assignments;
            }

            return [$this->overrideQueryPart('insert', $sql, [], $this->params)];
        }

        return [];
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
    public function getUpdateSql(Table $table, array $row, array $pkValues = []): array
    {
        $this->params = [];

        $sql = 'UPDATE ' . $this->delimit($table->name);
        $sql .= ' SET ';
        foreach ($row as $columnName => $value) {
            $value = $value instanceof ObjectReferenceInterface ? $value->getPrimaryKeyValue() : $value;
            $paramName = 'param_' . strval(count($this->params) + 1);
            $this->params[$paramName] = $value;
            $sql .= $this->delimit($columnName) . ' = :' . $paramName . ',';
        }
        $sql = rtrim($sql, ",");
        $sql .= ' WHERE ';
        foreach ($pkValues as $property => $value) {
            $pkColumn = $this->options->mappingCollection->getPropertyMapping($property)->getFullColumnName();
            $paramName = 'param_' . strval(count($this->params) + 1);
            $params[$paramName] = $value;
            $sql .= $this->delimit($pkColumn) . ' = :' . $paramName;
        }

        return [$this->overrideQueryPart('update', $sql, [], $this->params, $this->options->query)];
    }

    public function getReplaceSql(Table $table, array $row, array $pkValues): array
    {
        // TODO: Implement getReplaceSql() method.
    }
}
