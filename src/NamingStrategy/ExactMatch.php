<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * A 'dummy' naming strategy that does not perform any conversion.
 */
class ExactMatch implements NamingStrategyInterface
{
    public function convertName(
        string $name,
        int $type,
        ?PropertyMapping $propertyMapping = null
    ): string {
        return $name;
    }

    /**
     * Convert the plural form of a to-many relationship property into its singular equivalent (eg. policies to policy)
     * @param string $name
     * @return string
     */
    public function dePluralise(string $name): string
    {
        if (substr($name, -4) == 'zzes') {
            return substr($name, 0, strlen($name) - 3);
        } elseif (substr($name, -4) == 'sses') {
            return substr($name, 0, strlen($name) - 3);
        } elseif (substr($name, -3) == 'ves') {
            return substr($name, 0, strlen($name) - 3) . 'f';
        } elseif (substr($name, -3) == 'ies') {
            return substr($name, 0, strlen($name) - 3) . 'y';
        } elseif (substr($name, -2) == 'es') {
            return substr($name, 0, strlen($name) - 2);
        } elseif (substr($name, -1) == 's') {
            return substr($name, 0, strlen($name) - 1);
        } elseif (substr($name, -1) == 'i') {
            return substr($name, 0, strlen($name) - 1) . 'us';
        } elseif (substr($name, -1) == 'a') {
            return substr($name, 0, strlen($name) - 1) . 'on';
        } elseif (strtolower($name) == 'children') {
            return substr($name, 0, 5);
        } elseif (strtolower($name) == 'people') {
            return substr($name, 0, 2) . 'rson'; //maintain capitalisation
        } elseif (substr($name, -4) == 'eaux') {
            return substr($name, 0, strlen($name) - 1);
        } elseif (substr($name, -3) == 'eux') {
            return substr($name, 0, strlen($name) - 1);
        } elseif (substr($name, -3) == 'oux') {
            return substr($name, 0, strlen($name) - 1);
        } elseif (substr($name, -3) == 'aux') {
            return substr($name, 0, strlen($name) - 2) . 'l';
        }
    }
}
