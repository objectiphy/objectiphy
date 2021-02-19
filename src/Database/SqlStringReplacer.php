<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Orm\ObjectHelper;
use Objectiphy\Objectiphy\Orm\ObjectMapper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Handles replacement of tokenised strings with the values they represent - eg. properties to columns; expressions to
 * values; tokens in prepared statements to literal values.
 */
class SqlStringReplacer
{
    private ObjectMapper $objectMapper;
    private DataTypeHandlerInterface $dataTypeHandler;
    private array $objectNames = [];
    private array $persistenceNames = [];
    private array $aliases = [];
    private string $databaseDelimiter = '`';
    private string $valueDelimiter = "'";
    private string $propertyPathDelimiter = '%';
    private string $escapeCharacter = '\\';
    private string $tokenPrefix = ':';
    private string $tokenSuffix = '';

    public function __construct(ObjectMapper $objectMapper, DataTypeHandlerInterface $dataTypeHandler)
    {
        $this->objectMapper = $objectMapper;
        $this->dataTypeHandler = $dataTypeHandler;
    }

    public function setDelimiters(
        string $databaseDelimiter = '`',
        string $valueDelimiter = "'",
        string $propertyPathDelimiter = '%',
        string $escapeCharacter = '\\',
        string $tokenPrefix = ':',
        string $tokenSuffix = ''
    ) {
        $this->databaseDelimiter = $databaseDelimiter;
        $this->valueDelimiter = $valueDelimiter;
        $this->propertyPathDelimiter = $propertyPathDelimiter;
        $this->escapeCharacter = $escapeCharacter;
        $this->tokenPrefix = $tokenPrefix;
        $this->tokenSuffix = $tokenSuffix;
    }

    /**
     * Replace prepared statement parameters with actual values (for debugging output only, not for execution!)
     * @param string $queryString Parameterised SQL string.
     * @param array $params Parameter values to replace tokens with.
     * @return string SQL string with values instead of parameters.
     */
    public function replaceTokens(
        string $queryString,
        array $params
    ): string {
        if (count($params)) {
            //Reverse array as we don't want to replace param_10 with column name for param_1 followed by a zero!
            foreach (array_reverse($params) as $key => $value) {
                $queryString = str_replace(
                    $this->tokenPrefix . $key . $this->tokenSuffix,
                    (in_array($value, [null, true, false], true)
                        ? var_export($value, true)
                        : $this->delimit($value, $this->valueDelimiter, '')),
                    $queryString
                );
            }
        }

        return $queryString;
    }

    /**
     * Build arrays of strings to replace and what to replace them with.
     * @param QueryInterface $query
     * @param MappingCollection $mappingCollection
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function prepareReplacements(QueryInterface $query, MappingCollection $mappingCollection): void
    {
        $this->objectNames = [];
        $this->persistenceNames = [];
        $this->aliases = [];
        $mappingCollection = $mappingCollection ?? $this->getMappingCollection($query->getClassName());

        $propertiesUsed = $query->getPropertyPaths();
        foreach ($propertiesUsed as $propertyPath) {
            $this->objectNames[] = $this->delimit($propertyPath, $this->propertyPathDelimiter, '');
            $alias = '';
            $persistenceValue = $this->getPersistenceValueForField($query, $propertyPath, $mappingCollection, $alias);
            $this->persistenceNames[] = $persistenceValue;
            $this->aliases[] = $alias ?: $persistenceValue;
        }
        if (!empty($mappingCollection)) {
            $tables = $mappingCollection->getTables();
            foreach ($tables as $class => $table) {
                $this->objectNames[] = $class;
                $this->persistenceNames[] = $this->delimit($table->name);
            }
        }
    }

    /**
     * Wrap components (separated by $separator) with the specified delimiter - eg. to convert
     * "database.table.column" to "`database`.`table`.`column`". As the input does not come
     * from a user, but from mapping definitions, we will not sanitize in case there is a
     * reason a for a developer wanting to break out of the delimiter to do something filthy
     * (and literal values will be passed in as parameters of a prepared statement anyway).
     * @param string $value Database/Table/Column separated by a dot, or field expression/value.
     * @param string|null $delimiter Character to wrap around the component parts of the string.
     * @param string $separator Character that separates components that need to be delimited
     * @return string Delimited string.
     */
    public function delimit(string $value, ?string $delimiter = null, string $separator = '.', bool $delimitEmptyString = false): string
    {
        $delimiter ??= $this->databaseDelimiter;
        $delimited = '';
        if (strlen($value) > 0) {
            $value = str_replace($delimiter, '', $value); //Don't double-up
            if ($separator) {
                $delimited = $delimiter . implode($delimiter . $separator . $delimiter, explode($separator, $value)) . $delimiter;
            } else {
                $delimited = $delimiter . $value . $delimiter;
            }
        } elseif ($delimitEmptyString) {
            $delimited = $delimiter . $value . $delimiter;
        }

        return $delimited;
    }

    /**
     * @param string $subject
     * @param bool $useAliases Whether or not to append aliases to the column names using the alias joiner.
     * @param string $aliasJoiner String to use to join alias onto column name (only if there is an alias to use).
     * @return string
     * @throws ObjectiphyException
     */
    public function replaceNames(string $subject, bool $useAliases = false, string $aliasJoiner = ' AS '): string
    {
        if (!isset($this->objectNames)) {
            throw new ObjectiphyException('Please call prepareReplacements method before attempting to replace.');
        }

        if ($useAliases) {
            $aliasedPersistence = array_map(
                function ($persistence, $alias) use ($aliasJoiner) {
                    return $persistence . ($alias ? $aliasJoiner . $alias : '');
                }, $this->persistenceNames, $this->aliases
            );
            $replaced = str_replace($this->objectNames, $aliasedPersistence, $subject);
        } else {
            $replaced = str_replace($this->objectNames, $this->persistenceNames, $subject);
        }

        return $replaced;
    }

    /**
     * For a custom queries, if you use a column (not a property), an expression, or a function, you must
     * delimit values ('), properties (%), and columns (`) yourself. If no unescaped delimiter is present,
     * it is either a property, or a literal value. We detect a property by seeing if the property path
     * exists, otherwise fall back to a literal value.
     * @param QueryInterface $query
     * @param mixed $fieldValue Literal value, property path, expression, or database table/column.
     * @param ?string $alias If value relates to a property, returns the column alias
     * @param MappingCollection $mappingCollection
     * @return string
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function getPersistenceValueForField(
        QueryInterface $query,
        $fieldValue,
        MappingCollection $mappingCollection,
        ?string &$alias = null
    ): string {
        $persistenceValue = null;
        if (is_string($fieldValue)) {
            //Check if already delimited - if so, use as is
            if ($this->checkDelimited($fieldValue)) {
                $persistenceValue = strval($fieldValue);
            } elseif ($this->checkPropertyPath($fieldValue, $alias, $query, $mappingCollection)) {
                //Check if is is a property path
                $persistenceValue = strval($fieldValue);
            }
        }
        if ($persistenceValue === null) {
            $persistenceValue = $this->checkLiteralValue($fieldValue, $query);
        }

        return $this->replaceLiteralsWithParams($query, $persistenceValue);
    }

    /**
     * @param string $className
     * @return MappingCollection|null
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function getMappingCollection(string $className): ?MappingCollection
    {
        if ($className && substr($className, 0, 1) != $this->databaseDelimiter && class_exists($className)) {
            return $this->objectMapper->getMappingCollectionForClass($className);
        }

        return null;
    }

    private function replaceLiteralsWithParams(QueryInterface $query, string $fieldValue): string
    {
        $matches = [];
        $search = [];
        $replace = [];
        preg_match_all("/'((?:\\" . $this->escapeCharacter . "'|[^'])*)'/", $fieldValue, $matches);
        if (isset($matches[1])) { //$matches[0] includes the quotes, $matches[1] does not
            foreach ($matches[1] ?? [] as $index => $match) {
                $paramName = $query->addParam($match);
                $search[] = $matches[0][$index];
                $replace[] = $this->tokenPrefix . $paramName . $this->tokenSuffix;
            }
        }
        if ($search && $replace) {
            $fieldValue = str_replace($search, $replace, $fieldValue);
        }

        return $fieldValue;
    }

    private function checkDelimited(string $fieldValue): bool
    {
        if ($this->countUnescaped($fieldValue, $this->propertyPathDelimiter) >= 2
            || $this->countUnescaped($fieldValue, $this->databaseDelimiter) >= 2
            || $this->countUnescaped($fieldValue, $this->valueDelimiter) >= 2) {
            return true;
        }

        return false;
    }

    /**
     * @param string $fieldValue
     * @param $alias
     * @param QueryInterface $query
     * @param MappingCollection $mappingCollection
     * @return bool
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function checkPropertyPath(string &$fieldValue, &$alias, QueryInterface $query, MappingCollection $mappingCollection): bool
    {
        if (strpos($fieldValue, '.') !== false) {
            //Check if first part is a custom join alias
            $alias = strtok($fieldValue, '.');
            $aliasClass = $query->getClassForAlias($alias);
            if ($aliasClass) {
                if ($mappingCollection = $this->getMappingCollection($aliasClass)) {
                    $propertyPath = substr($fieldValue, strpos($fieldValue, '.') + 1);
                    $propertyMapping = $mappingCollection->getPropertyMapping($propertyPath);
                    if ($propertyMapping) {
                        $fieldValue = $this->delimit($alias . '.' . $propertyMapping->getShortColumnName(false));
                        $alias = $this->delimit($propertyMapping->getAlias());
                        return true;
                    }
                }
            }
        }

        if ($mappingCollection = $mappingCollection ?? $this->getMappingCollection($query->getClassName())) {
            $propertyMapping = $mappingCollection->getPropertyMapping($fieldValue);
            if ($propertyMapping) {
                $explicitTable = '';
                if (strpos($fieldValue, '.') === false && strpos(
                        $query->getClassName(),
                        $this->databaseDelimiter
                    ) !== false) {
                    //We are fetching from an explicitly specified table name - use it for any root properties
                    $explicitTable = $query->getClassName();
                }
                $alias = $this->delimit($propertyMapping->getAlias());
                $fieldValue = $this->delimit($propertyMapping->getFullColumnName($explicitTable));
                return true;
            }
        }

        return false;
    }

    private function checkLiteralValue($fieldValue, QueryInterface $query)
    {
        //If not, treat it as a value (and escape quotes if not already done)
        if (is_object($fieldValue) && !($fieldValue instanceof \DateTimeInterface)) {
            //We have an object - extract the primary key value if we can
            if ($fieldValue instanceof ObjectReferenceInterface) {
                $pkValues = $fieldValue->getPkValues();
            } else {
                $mappingCollection = $this->objectMapper->getMappingCollectionForClass($query->getClassName());
                if ($mappingCollection) {
                    $pkValues = $mappingCollection->getPrimaryKeyValues($fieldValue);
                }
            }
            $fieldValue = !empty($pkValues) ? reset($pkValues) : 'null';
        } else {
            $this->dataTypeHandler->toPersistenceValue($fieldValue);
            if ($fieldValue === null) {
                $fieldValue = "null";
            } elseif (is_scalar($fieldValue)) {
                $fieldValue = strval($fieldValue);
                $escapedChar = $this->escapeCharacter . $this->valueDelimiter;
                if (strpos($fieldValue, $escapedChar) !== false) {
                    $fieldValue = str_replace($this->valueDelimiter, $escapedChar, $fieldValue);
                }
                $fieldValue = $this->delimit($fieldValue, $this->valueDelimiter, '', true);
            }
        }

        return strval($fieldValue);
    }

    private function countUnescaped(string $value, string $delimiter): int
    {
        return substr_count($value, $delimiter) - substr_count($value, $this->escapeCharacter . $delimiter);
    }
}
