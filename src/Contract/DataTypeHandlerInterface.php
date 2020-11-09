<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

interface DataTypeHandlerInterface
{
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'boolean';
    public const TYPE_INT = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_DATETIME_IMMUTABLE = 'datetimeimmutable';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME_STRING = 'datetimestring';
    
    /**
     * Convert the given value to a format acceptable to the persistence layer (eg. MySql).
     * @param mixed &$value The value to convert.
     * @param string $dataType Optionally specify one of the data type constants or a class name.
     * @param string $format Optionally specify a format string if applicable to the data type.
     * @return bool Whether or not the value was successfully converted.
     */
    public function toPersistenceValue(&$value, ?string $dataType = null, string $format): bool;

    /**
     * Convert the given value to a format acceptable to a PHP object (eg. from a date string to a \DateTime)
     * @param mixed &$value The value to convert.
     * @param string $dataType Optionally specify one of the data type constants or a class name.
     * @param string $format Optionally specify a format string if applicable to the data type.
     * @param string $format If the data type requires a format (eg. datetimestring), specify it here.
     * @return bool Whether or not the value was successfully converted.
     */
    public function toObjectValue(&$value, ?string $dataType = null, ?string $format): bool;
}
