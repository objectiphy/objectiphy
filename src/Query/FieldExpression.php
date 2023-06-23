<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryPartInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
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

    /**
     * @var string Alias to use in the results when selecting this field.
     */
    private string $alias = '';

    /**
     * @var array Array of key values pairs, which, if specified will translate data values (typically 
     * using a CASE WHEN statement).
     */
    private array $dataMap = [];

    public function __construct($expression = null, array $dataMap = [])
    {
        $this->setExpression($expression);
        $this->dataMap = $dataMap;
    }

    public function __toString(): string
    {
        $delimiter = $this->isPropertyPath ? '%' : '';
        return $delimiter . strval($this->expression) . $delimiter;
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
        return $this->isPropertyPath ? str_replace('%', '', $this->expression) : '';
    }

    public function getDataMap(): array
    {
        return $this->dataMap;
    }
    
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Set the value of the expression - if anything is wrapped in percent signs, it will be treated as a property path.
     * Anything outside of the percent signs will be treated as a string literal. If there are no percent signs, spaces,
     * quotes, backticks, or brackets, the whole thing will be treated as a property path (as this is the most common
     * use case).
     * @param $value 
     */
    public function setExpression($value): void
    {
        $this->isPropertyPath = false;
        $this->expression = $value;
        if (is_string($value)) {
            $this->isPropertyPath = !(preg_match("/(\s|\%|\'|`|\()/", $value));
            //$this->isPropertyPath = !(preg_match("/(\s|\%|\')/", $value));
            //If the whole thing is wrapped in % though, it could still be a property path...
            $count = substr_count($value, '%');
            if ($count == 2 && strpos($value, '%') === 0 && strrpos($value, '%') === strlen($value) - 1) {
                $this->isPropertyPath = !(preg_match("/(\s|\'|`|\()/", $value));
                //$this->isPropertyPath = !(preg_match("/(\s|\%|\')/", $value));
            }
        }
    }

    public function setAlias(string $alias)
    {
        $this->alias = $alias;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return array Array of property paths used in the expression. Eg. an expression such as
     * CONCAT(%child.propertyOne%, '_', %otherChild.otherProperty%) would yield the following array:
     * ['child.propertyOne', 'otherChild.otherProperty'].
     */
    public function getPropertyPaths(): array
    {
        $paths = [];
        if ($this->isPropertyPath) {
            $paths[] = str_replace('%', '', $this->expression) ?: null;
        } elseif (is_string($this->expression)) {
            $match = [];
            preg_match('/\%(.*?)\%/', $this->expression, $match);
            $property = $match[1] ?? '';
            if ($property) {
                $paths[] = $property;
            }
        }

        return array_filter($paths);
    }
}
