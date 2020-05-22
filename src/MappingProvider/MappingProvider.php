<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * This is just a base delegate that other providers can decorate depending on how they get their mapping information.
 */
class MappingProvider implements MappingProviderInterface
{
    public function getTableMapping(\ReflectionClass $reflectionClass): Table
    {
        return new Table();
    }
    
    public function getColumnMapping(\ReflectionProperty $reflectionProperty): Column
    {
        return new Column();
    }

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): Relationship
    {
        return new Relationship();
    }
}
