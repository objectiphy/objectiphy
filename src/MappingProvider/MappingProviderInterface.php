<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

interface MappingProviderInterface
{
    public function getTableMapping(\ReflectionClass $reflectionClass): Table;
    public function getColumnMapping(\ReflectionProperty $reflectionProperty): Column;
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): Relationship;
}
