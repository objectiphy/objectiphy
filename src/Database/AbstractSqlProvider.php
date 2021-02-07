<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\SqlProviderInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Base class for SQL providers.
 */
class AbstractSqlProvider implements SqlProviderInterface
{
    protected array $params = [];
    protected array $queryOverrides = [];
    protected string $sql = '';
    protected MappingCollection $mappingCollection;
    protected DataTypeHandlerInterface $dataTypeHandler;
    protected array $objectNames = [];
    protected array $persistenceNames = [];
    protected array $aliases = [];

    public function __construct(DataTypeHandlerInterface $dataTypeHandler)
    {
        $this->dataTypeHandler = $dataTypeHandler;
    }

    public function setMappingCollection(MappingCollection $mappingCollection): void
    {
        $this->mappingCollection = $mappingCollection;
    }

    /**
     * Return the parameter values to bind to the SQL statement.
     * @return array Parameter key/value pairs to bind to the prepared statement.
     */
    public function getQueryParams(): array
    {
        $this->params = $this->params ?? [];

        return $this->params;
    }

    public function setQueryParams(array $params = []): void
    {
        $this->params = $params;
    }

    /**
     * Replace prepared statement parameters with actual values (for debugging output only, not for execution!)
     * @param string $queryString Parameterised SQL string.
     * @param array $params Parameter values to replace tokens with.
     * @return string SQL string with values instead of parameters.
     */
    public function replaceTokens(string $queryString, array $params): string
    {
        if (count($params)) {
            foreach (array_reverse($params) as $key => $value) { //Don't want to replace param_10 with column name for param_1 followed by a zero!
                $queryString = str_replace(
                    ':' . $key,
                    (in_array($value, [null, true, false], true) ? var_export($value, true) : "'$value'"),
                    $queryString
                );
            }
        }

        return $queryString;
    }

    /**
     * Convert "database.table.column" to "`database`.`table`.`column`". As the input does not
     * come from a user, but from mapping definitions, we will not sanitize in case there is a
     * reason a for a developer wanting to break out of the backticks to do something filthy.
     * @param string $tableColumnString Database/Table/Column separated by a dot.
     * @param string $delimiter Character to wrap tables and columns in.
     * @return string Backtick separated string equivalent.
     */
    protected function delimit(string $tableColumnString, string $delimiter = '`'): string
    {
        $delimited = '';
        if ($tableColumnString) {
            $delimited = str_replace($delimiter, '', $tableColumnString); //Don't double-up
            $delimited = $delimiter . implode("$delimiter.$delimiter", explode('.', $delimited)) . $delimiter;
        }

        return $delimited;
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
