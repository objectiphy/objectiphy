<?php

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

class MappingProviderAnnotation implements MappingProviderInterface
{
    private AnnotationReaderInterface $annotationReader;

    public function __construct(AnnotationReaderInterface $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    public function getTableMapping(\ReflectionClass $reflectionClass)
    {
        $table = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
        if (!$table) {
            //Try Doctrine annotation

        }

        return $table;
    }

    public function getColumnMapping(\ReflectionProperty $reflectionProperty)
    {
        $column = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
        //Anything missing? Try filling in with Doctrine annotations...

        return $column;
    }

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty)
    {
        $relationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Relationship::class);
        //Anything missing? Try filling in with Doctrine annotations...

        return $relationship;
    }
}
