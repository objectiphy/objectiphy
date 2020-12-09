<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;

/**
 * Yields something that can be resolved to a value - typically just a property path, but can also 
 * accommodate a function - eg. COUNT, AVG(`someOtherToManyPropery`), CONCAT(`property`, '_', `otherProperty`).
 * Might update this later to support sub queries, but that's a bit ambitious for now.
 */
class FieldExpression implements QueryPartInterface, PropertyPathConsumerInterface
{
    /**
     * @var mixed $expression Typically a string consisting of just a property path, but could be
     * anything. It could hold a function, or the value to be used in a CriteriaExpression (eg. it
     * could hold a \DateTime value). Property paths must always be enclosed in backticks, as without
     * that delimiter, they will be interpreted as plain strings.
     */
    private $expression;

    /**
     * @var bool Whether or not the expression is a simple property path string.
     */
    private bool $isPropertyPath;

    public function __construct($expression = null, $isPropertyPath = true)
    {
        if ($isPropertyPath) {
            $this->setPropertyPath($expression);
        } else {
            $this->setExpression($expression);
        }
    }

    public function __toString(): string
    {
        $expression = $this->isPropertyPath
            ? '`' . str_replace('`', '', $this->expression) . '`'
            : $this->expression;
        return strval($expression);
    }

    /**
     * @return bool Whether or not the expression is a simple property path.
     */
    public function isPropertyPath(): bool
    {
        return $this->isPropertyPath;
    }

    public function getPropertyPath(): ?string
    {
        return $this->isPropertyPath ? $this->expression : null;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function setPropertyPath(string $propertyPath): void
    {
        $this->expression = $propertyPath;
        $this->isPropertyPath = true;
    }

    /**
     * Set the value of the expression - will try to automatically detect a property path if wrapped in backticks.
     * @param $value 
     */
    public function setExpression($value): void
    {
        $this->isPropertyPath = false; //If it turns out to be a property path, it will get changed.
        $this->expression = $value;
        if (is_string($value)) {
            $count = 0;
            $potentialPropertyPath = str_replace('`', '', $value, $count);
            if ($count == 2 && strpos($value, '`') === 0 && strrpos($value, '`') === strlen($value)) {
                $this->setPropertyPath($value);
            }
        }
    }

    /**
     * @return array Array of property paths used in the expression. Eg. an expression such as
     * CONCAT(`child.propertyOne`, '_', `otherChild.otherProperty`) would yield the following array:
     * ['child.propertyOne', 'otherChild.otherProperty'].
     */
    public function getPropertyPaths(): array
    {
        $paths = [];
        if ($this->isPropertyPath) {
            $paths[] = $this->expression;
        } elseif (is_string($this->expression)) {
            $match = [];
            preg_match('/`(.*?)`/', $this->expression, $match);
            $property = $match[1] ?? '';
            if ($property) {
                $paths[] = $property;
            }
        }

        return $paths;
    }
}
