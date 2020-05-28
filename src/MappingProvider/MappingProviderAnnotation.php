<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * Reads Objectiphy annotations, which take precedence over any Doctrine ones supplied by the component we are 
 * decorating.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class MappingProviderAnnotation implements MappingProviderInterface
{
    private MappingProviderInterface $mappingProvider;
    private AnnotationReaderInterface $annotationReader;

    public function __construct(MappingProviderInterface $mappingProvider, AnnotationReaderInterface $annotationReader)
    {
        $this->mappingProvider = $mappingProvider;
        $this->annotationReader = $annotationReader;
    }

    /**
     * Populate a Table mapping class based on annotations.
     * @param \ReflectionClass $reflectionClass
     * @return object|null
     */
    public function getTableMapping(\ReflectionClass $reflectionClass): Table
    {
        $table = $this->mappingProvider->getTableMapping($reflectionClass);
        $objectiphyTable = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);

        return $this->decorate($table, $objectiphyTable);
    }

    /**
     * Populate a Column mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @return object|null
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty): Column
    {
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty);
        $objectiphyColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
        
        return $this->decorate($column, $objectiphyColumn);
    }

    /**
     * Populate a Relationship mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @return object|null
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): Relationship
    {
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
        $objectiphyRelationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Relationship::class);
        
        return $this->decorate($relationship, $objectiphyRelationship);
    }

    /**
     * Takes a mapping object (Table, Column, Relationship), and replaces property values with the properties of an 
     * equivalent object, overriding the base implementation.
     * @param $component
     * @param $decorator
     */
    private function decorate($component, $decorator)
    {
        if (get_class($component) == get_class($decorator)) {
            foreach (get_object_vars($decorator) as $property => $value) {
                $component->$property = $value;
            }
        }
    }
}
