<?php

namespace Objectiphy\Objectiphy\MappingProvider;

interface MappingProviderInterface
{
    public function getMappingCollectionForClass(string $className);
}