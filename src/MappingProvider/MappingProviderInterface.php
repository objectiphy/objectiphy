<?php

namespace Objectiphy\Objectiphy\MappingProvider;

interface MappingProviderInterface
{
    public function getTableMapping(\ReflectionClass $reflectionClass);
    public function getColumnMapping(\ReflectionProperty $reflectionProperty);
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty);
}
