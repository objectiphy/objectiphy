<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * This is just a base component that other providers can decorate depending on how they get their mapping information.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class MappingProvider implements MappingProviderInterface
{
    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped): Table
    {
        $wasMapped = false;
        return new Table();
    }
    
    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped): Column
    {
        $wasMapped = false;
        return new Column();
    }

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped): Relationship
    {
        $wasMapped = false;
        return new Relationship();
    }
}
