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
     * Split the given name into words. This base implementation will split snake_case, camelCase, and PascalCase, but
     * you can override it if required.
     * @param string $name
     * @return array
     */
    public function splitIntoWords(string $name): array
    {
        //Pascal and camel
        $converted = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), ' ');
        if ($converted != $name) { //We detected camel or pascal case
            return explode(' ', $converted);
        } else { //Just assume snake (or a single word)
            return explode('_', $converted);
        }
    }

    /**
     * Convert the plural form of a to-many relationship property into its singular equivalent (eg. policies to policy).
     * If this fails, you will just have to define it explicitly in the mapping instead of letting us guess.
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
        } elseif (strtolower($name) == 'indices') {
            return substr($name, 0, 3) . 'ex'; //maintain capitalisation
        } elseif (substr($name, -2) == 'es') {
            return substr($name, 0, strlen($name) - 2);
        } elseif (substr($name, -1) == 's') {
            return substr($name, 0, strlen($name) - 1);
        } elseif (strtolower($name) == 'children') {
            return substr($name, 0, 5); //maintain capitalisation
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
