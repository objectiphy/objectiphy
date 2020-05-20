<?php

declare(strict_types=1);

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

    /**
     * Populate a Table mapping class based on annotations.
     * @param \ReflectionClass $reflectionClass
     * @return object|null
     */
    public function getTableMapping(\ReflectionClass $reflectionClass): ?Table
    {
        return $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
    }

    /**
     * Populate a Column mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @return object|null
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty): ?Column
    {
        return $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
    }

    /**
     * Populate a Relationship mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @return object|null
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): ?Relationship
    {
        return $this->annotationReader->getPropertyAnnotation($reflectionProperty, Relationship::class);
    }
}
