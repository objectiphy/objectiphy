<?php

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\MappingCollection;

class AnnotationMappingProvider implements MappingProviderInterface
{
    private AnnotationReader $annotationReader;

    public function __construct(AnnotationReader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    public function getMappingCollectionForClass(string $className, array $parentProperties = [])
    {
        $reflectionClass = new \ReflectionClass($className);
        $mappingCollection = new MappingCollection($className);
        $table = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $column = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
            $relationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Relationship::class);

            //Add table, column, relationship to current collection

            if ($relationship->childClass) {
                $parentProperties[] = $reflectionProperty->getName();
                $childMappingCollection = $this->getMappingCollectionForClass($relationship->childClass, $parentProperties);

                //Merge child collection into this one...?

            }
        }
    }
}