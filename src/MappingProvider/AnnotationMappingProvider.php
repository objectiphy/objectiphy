<?php

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\MappingCollection;
use Objectiphy\Objectiphy\Orm\ConfigOptions;

class AnnotationMappingProvider implements MappingProviderInterface
{
    private AnnotationReader $annotationReader;
    private ConfigOptions $config;
    private MappingCollection $mappingCollection;

    public function __construct(AnnotationReader $annotationReader, ConfigOptions $config)
    {
        $this->annotationReader = $annotationReader;
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
        $table = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $column = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
            $relationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Relationship::class);

            //Add table, column, relationship to current collection

            //Create a property mapping object - pass it to shouldAddChildMappings instead of separate
            //parent property name, parent class name, child property name, and relationship type

            if ($this->shouldAddChildMappings($relationship, end($parentProperties), $className, $reflectionProperty->getName())) {
                $parentProperties[] = $reflectionProperty->getName();
                $this->populateMappingCollection($relationship->childClass, $parentProperties);
            }
        }
    }

    //Refactor this to use a property mapping object?
    private function shouldAddChildMappings(Relationship $relationship, string $parentProperty, string $className, string $propertyName)
    {
        $result = false;
        if ($relationship->childClass ?? false && $relationship->isEager($this->config)) {
            $result = $this->mappingCollection->isRelationshipMapped($parentProperty, $className, $propertyName);
        }
        
        return $result;
    }
}