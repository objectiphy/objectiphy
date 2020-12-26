<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Represents the assignment of a value to a property for persistence
 */
class AssignmentExpression implements QueryPartInterface, PropertyPathConsumerInterface
{
    private string $propertyPath;
    private $value;

    public function __construct($propertyPath = null, $value = null)
    {
        if ($propertyPath !== null) {
            $this->setPropertyPath($propertyPath);
            $this->setValue($value);
        }
    }

    /**
     * @param string $proprtyPath Property to which a value needs to be assigned.
     */
    public function setPropertyPath(string $proprtyPath): void
    {
        $this->propertyPath = $proprtyPath;
    }

    /**
     * @param mixed $value Value to assign.
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * Return query with parameters resolved (for display only, NOT for execution!)
     * @return string
     */
    public function __toString(): string
    {
        $params = [];
        $assignmentString = $this->toString($params);
        foreach ($params as $key => $value) {
            $assignmentString = str_replace(':' . $key, "'" . $value . "'", $assignmentString);
        }

        return $assignmentString;
    }

    /**
     * Return parameterised query
     * @param array $params
     * @return string
     */
    public function toString(array &$params = []): string
    {
        $string = "`$this->propertyPath` = ";
        if ($this->value === null) {
            $string .= 'null';
        } else {
            //If there are quotes in the value, extract anything between quotes...?

            $paramCount = count($params) + 1;
            $params['param_' . $paramCount] = $this->value;
            $string .= ':param_' . $paramCount;
        }
        
        return $string;
    }

    /**
     * @return array Array of property paths used in the expression.
     */
    public function getPropertyPaths(): array
    {
        $paths = [$this->propertyPath];
        if (is_string($this->value)) {
            $match = [];
            preg_match('/`(.*?)`/', $this->value, $match);
            $property = $match[1] ?? '';
            if ($property) {
                $paths[] = $property;
            }
        }

        return $paths;
    }
}
