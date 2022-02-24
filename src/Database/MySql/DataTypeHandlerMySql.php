<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database\MySql;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Converts PHP data types into MySQL ones (eg. \DateTime to yyyy-mm-dd, boolean to 0 or 1)
 */
class DataTypeHandlerMySql implements DataTypeHandlerInterface
{
    //MySQL functions which can be used without any arguments, or with just an asterisk
    public const FUNCTION_IDENTIFIERS = [
        'COUNT(',
        'CURDATE(',
        'CURRENT_TIMESTAMP(',
        'CURRENT_USER(',
        'CURTIME(',
        'DATABASE(',
        'FOUND_ROWS(',
        'LAST_INSERT_ID(',
        'LOCALTIMESTAMP(',
        'LOCALTIME(',
        'NOW(',
        'PI(',
        'PS_CURRENT_THREAD_ID(',
        'RAND(',
        'RELEASE_ALL_LOCKS(',
        'ROLES_GRAPHML(',
        'ROW_COUNT(',
        'ROW_NUMBER(',
        'SCHEMA(',
        'SESSION_USER(',
        'SYSTEM_USER(',
        'USER(',
        'UTC_DATE(',
        'UTC_TIME(',
        'UTC_TIMESTAMP(',
        'UUID(',
        'UUID_SHORT(',
        'VERSION(',
    ];

    /**
     * Convert the given value to a format acceptable to the persistence layer (eg. MySql).
     * @param mixed &$value The value to convert.
     * @param string|null $dataType Optionally specify one of the data type constants or a class name.
     * @param string $format Optionally specify a format string if applicable to the data type.
     * @return bool Whether or not the value was successfully converted.
     */
    public function toPersistenceValue(&$value, ?string $dataType = null, string $format = ''): bool
    {
        if (is_object($value)) {
            switch (true) {
                case $value instanceof \DateTimeInterface:
                    $value = $value->format('Y-m-d H:i:s');
                    break;
                case $value instanceof ObjectReferenceInterface:
                    $value = $value->getPrimaryKeyValue();
                    break;
                default:
                    if (substr(ObjectHelper::getObjectClassName($value), -8) == 'DateTime' && method_exists($value, 'format')) {
                        try { //Assume an extended DateTime (cannot be inherited, but could use composition)
                            $formatted = $value->format('Y-m-d H:i:s');
                            if ($formatted && is_string($formatted)) {
                                $value = $formatted;
                            }
                        } catch (\Exception $ex) { }
                    }
                    break;
            }
        }

        //Booleans need to be returned as 0 or 1 to avoid complaints from MySQL
        switch (strtolower($dataType ?? '')) {
            case 'bool':
            case 'boolean':
            case 'int':
            case 'integer':
                $value = $value === null ? $value : intval($value);
                break;
            default:
                $value = is_bool($value) ? intval($value) : $value;
                break;
        }

        //Convert formatted Date/Time string back into a format accepted by MySQL
        if (in_array($dataType, ['datestring', 'datetimestring']) && $format) {
            $dateTime = \DateTime::createFromFormat($format, $value);
            if ($dateTime) {
                $formatted = $dateTime->format('Y-m-d H:i:s');
                if ($formatted && is_string($formatted)) {
                    $value = $formatted;
                }
            }
        }

        return is_scalar($value);
    }

    /**
     * Convert the given value to a format acceptable to a PHP object (eg. from a date string to a \DateTime)
     * @param mixed &$value The value to convert.
     * @param string|null $dataType Optionally specify one of the data type constants or a class name.
     * @param string|null $format If the data type requires a format (eg. datetimestring), specify it here.
     * @param bool $nullable Whether or not to allow null values.
     * @return bool Whether or not the value was successfully converted.
     * @throws \Exception
     */
    public function toObjectValue(&$value, ?string $dataType = null, ?string $format = null, $nullable = true): bool
    {
        $valueSettable = false;
        switch (strtolower(str_replace('\\', '', $dataType))) {
            case 'datetime':
            case 'date':
            case 'date_time':
                $valueToSet = $value === null ? null : ($value instanceof \DateTimeInterface ? $value : new \DateTime($value ?? ''));
                $valueSettable = true;
                break;
            case 'datetimeimmutable':
            case 'datetime_immutable':
            case 'date_time_immutable':
                $valueToSet = $value === null ? null : ($value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable($value ?? ''));
                $valueSettable = true;
                break;
            case 'datetimestring':
            case 'date_time_string':
            case 'datestring':
            case 'date_string':
                $format = $format ?: 'Y-m-d H:i:s';
                $dateValue =  ($value instanceof \DateTimeInterface ? $value : new \DateTime($value ?? ''));
                $valueToSet = $dateValue ? $dateValue->format($format) : $value;
                $valueSettable = true;
                break;
            case 'int':
            case 'integer':
                $valueToSet = intval($value);
                $valueSettable = true;
                break;
            case 'float':
            case 'real':
            case 'decimal':
            case 'double':
                $valueToSet = floatval($value);
                $valueSettable = true;
                break;
            case 'bool':
            case 'boolean':
                $valueToSet = $value ? (in_array(strtolower(strval($value)), ['false', '0']) ? false : true) : false;
                $valueSettable = true;
                break;
            case 'string':
                $valueToSet = $format ? sprintf($format, strval($value)) : strval($value);
                $valueSettable = true;
                break;
            default:
                if ($dataType === null
                    || in_array($dataType, ['\Traversable', 'array', '\iterable']) && (is_iterable($value) || is_null($value))
                    || $value instanceof $dataType
                    || ($value === null && class_exists($dataType))
                    || ($dataType != 'array' && !class_exists($dataType) && !is_object($value))
                    || ($value instanceof \Closure)
                ) {
                    $valueToSet = $value;
                    $valueSettable = true;
                }
                break;
        }

        if ($valueSettable && ($nullable || $value !== null)) {
            $value = $valueToSet;
            return true;
        }

        return false;
    }

    public function getFunctionIdentifiers(): array
    {
        return static::FUNCTION_IDENTIFIERS;
    }
}
