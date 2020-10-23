<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

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
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return object | null
     */
    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped = null): Table
    {
        $table = $this->mappingProvider->getTableMapping($reflectionClass, $wasMapped);
        $objectiphyTable = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
        $wasMapped = $wasMapped || $objectiphyTable;
        $hostClassName = $reflectionClass->getName();
        $hostProperty = '';

        return $this->decorate($hostClassName, $hostProperty, $table, $objectiphyTable);
    }

    /**
     * Populate a Column mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return object | null
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Column
    {
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $wasMapped);
        $objectiphyColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
        $wasMapped = $wasMapped || $objectiphyColumn;
        $hostClassName = $reflectionProperty->getDeclaringClass()->getName();
        $hostProperty = $reflectionProperty->getName();

        return $this->decorate($hostClassName, $hostProperty, $column, $objectiphyColumn);
    }

    /**
     * Populate a Relationship mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return object | null
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Relationship
    {
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $wasMapped);
        $objectiphyRelationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Relationship::class);
        $wasMapped = $wasMapped || $objectiphyRelationship;
        $hostClassName = $reflectionProperty->getDeclaringClass()->getName();
        $hostProperty = $reflectionProperty->getName();
        
        return $this->decorate($hostClassName, $hostProperty, $relationship, $objectiphyRelationship);
    }

    /**
     * Takes a mapping object (Table, Column, Relationship), and replaces property values with the properties of an 
     * equivalent object, overriding the base implementation. If the decorator's annotation did not specify a value for 
     * a property, the original value of the component is preserved.
     * @param object $component The object whose values may be overridden.
     * @param object $decorator The object which holds the values that take priority.
     */
    private function decorate(string $hostClassName, string $hostProperty, object $component, ?object $decorator = null)
    {
        if ($decorator) {
            if (get_class($component) == get_class($decorator)) {
                $attributesRead = $this->annotationReader->getAttributesRead($hostClassName, 'p:' . $hostProperty, get_class($decorator));
                foreach ($attributesRead as $property => $value) {
                    ObjectHelper::populateFromObject($decorator, $property, $component);
                }
            }
        }

        return $component;
    }
}
