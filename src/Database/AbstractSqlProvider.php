<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\SqlProviderInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Orm\ObjectMapper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Base class for SQL providers.
 */
class AbstractSqlProvider implements SqlProviderInterface
{
    protected array $params = [];
    protected array $queryOverrides = [];
    protected string $sql = '';
    protected ObjectMapper $objectMapper;
    protected MappingCollection $mappingCollection;
    protected DataTypeHandlerInterface $dataTypeHandler;
    protected array $objectNames = [];
    protected array $persistenceNames = [];
    protected array $aliases = [];

    public function __construct(ObjectMapper $objectMapper, DataTypeHandlerInterface $dataTypeHandler)
    {
        $this->objectMapper = $objectMapper;
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

    /**
     * For a custom join, if you use a column (not a property), you must delimit yourself
     * If you use an expression or function, you must delimit values ('), properties (%), and columns (`) yourself
     * So if no delimiter, it is either a property, or a literal value
     * Detect a property by seeing if the property path exists
     * Otherwise fall back to a value - and wrap in quotes (works for ints/dates as well as strings)
     * @param QueryInterface $query
     * @param string $fieldValue
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    protected function getSqlForField(QueryInterface $query, string $fieldValue): string
    {
        if (substr_count($fieldValue, '%') >= 2
            || substr_count($fieldValue, '`') >= 2
            || substr_count($fieldValue, "'") - substr_count($fieldValue, '\\\'') >= 2) {
            //Delimited - use as is
            return $fieldValue;
        } else {
            //Check if is is a property path
            if (strpos($fieldValue, '.') !== false) {
                //Check if first part is a custom join alias
                $alias = strtok($fieldValue, '.');
                $aliasClass = $query->getClassForAlias($alias);
                if ($aliasClass) {
                    $mappingCollection = $this->objectMapper->getMappingCollectionForClass($aliasClass);
                    $propertyPath = substr($fieldValue, strpos($fieldValue, '.') + 1);
                    $propertyMapping = $mappingCollection->getPropertyMapping($propertyPath);

                    return $this->delimit($alias . '.' . $propertyMapping->getShortColumnName(false));
                } else {
                    $propertyMapping = $this->mappingCollection->getPropertyMapping($fieldValue);
                    if ($propertyMapping) {
                        return $propertyMapping->getFullColumnName();
                    }
                }
            }

            //If not, treat it as a value (escape quotes if not already done)
            if (strpos($fieldValue, '\\\'') !== false) {
                $fieldValue = str_replace('\'', '\\\'', $fieldValue);
            }

            return "'" . $fieldValue . "'";
        }
    }
}
