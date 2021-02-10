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
     * @param string $propertyPath Property to which a value needs to be assigned.
     */
    public function setPropertyPath(string $propertyPath): void
    {
        $this->propertyPath = $propertyPath;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    /**
     * @param mixed $value Value to assign.
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    /**
     * Return query with parameters resolved (for display only, NOT for execution!)
     * @return string
     */
    public function __toString(): string
    {
        $string = "%$this->propertyPath% = ";
        $string .= $this->value === null ? 'null' : $this->value;

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
            preg_match('/\%(.*?)\%/', $this->value, $match);
            $property = $match[1] ?? '';
            if ($property) {
                $paths[] = $property;
            }
        }

        return $paths;
    }
}
