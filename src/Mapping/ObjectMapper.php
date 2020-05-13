<?php

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\MappingCollection;
use Objectiphy\Objectiphy\Orm\ConfigOptions;

class ObjectMapper
{
    private MappingProviderInterface $mappingProvider;
    private ConfigOptions $config;
    private MappingCollection $mappingCollection;

    public function __construct(MappingProviderInterface $mappingProvider, ConfigOptions $config)
    {
        $this->mappingProvider = $mappingProvider;
        $this->config = $config;
    }

    public function getMappingCollectionForClass(string $className)
    {
        $this->mappingCollection = new MappingCollection($className);
        $this->populateMappingCollection($className);
        
        return $this->mappingCollection;
    }
    
    private function populateMappingCollection(string $className, array $parentProperties = [])
    {
        $reflectionClass = new \ReflectionClass($className);
        $table = $this->mappingProvider->getTableMapping($reflectionClass);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty);
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
            if ($column || $relationship) {
                $propertyMapping = new PropertyMapping();
                $propertyMapping->className = $className;
                $propertyMapping->propertyName = $reflectionProperty->getName();
                $propertyMapping->column = $column;
                $propertyMapping->relationship = $relationship;
                $propertyMapping->parentProperties = $parentProperties;
                $this->mappingCollection->addMapping($propertyMapping);
                if ($this->shouldAddChildMappings($propertyMapping)) {
                    $parentProperties[] = $reflectionProperty->getName();
                    $this->populateMappingCollection($relationship->childClass, $parentProperties);
                }
            }
        }
    }

    private function shouldAddChildMappings(PropertyMapping $propertyMapping)
    {
        $result = false;
        $relationship = $propertyMapping->relationship;
        $parentProperty = end($propertyMapping->parentProperties);
        if ($relationship->childClass ?? false && $relationship->isEager($this->config)) {
            $result = $this->mappingCollection->isRelationshipMapped(
                $parentProperty, 
                $propertyMapping->className, 
                $propertyMapping->propertyName
            );
        }
        
        return $result;
    }
}