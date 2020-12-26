<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Orm\ObjectHelper;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Reads Objectiphy annotations, which take precedence over any Doctrine ones supplied by the component we are 
 * decorating.
 */
class MappingProviderAnnotation implements MappingProviderInterface
{
    use MappingProviderExceptionTrait;
    
    protected MappingProviderInterface $mappingProvider;
    protected AnnotationReaderInterface $annotationReader;

    public function __construct(MappingProviderInterface $mappingProvider, AnnotationReaderInterface $annotationReader)
    {
        $this->mappingProvider = $mappingProvider;
        $this->annotationReader = $annotationReader;
    }

    /**
     * Populate a Table mapping class based on annotations.
     * @param \ReflectionClass $reflectionClass
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Table
     * @throws MappingException
     * @throws \Throwable
     */
    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped = null): Table
    {
        try {
            $this->annotationReader->setThrowExceptions($this->throwExceptions);
            $table = $this->mappingProvider->getTableMapping($reflectionClass, $wasMapped);
            $objectiphyTable = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
            $wasMapped = $wasMapped || $objectiphyTable;
            $hostClassName = $reflectionClass->getName();
            $hostProperty = '';

            return $this->decorate($hostClassName, $hostProperty, $table, $objectiphyTable);
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Table();
        }
    }

    /**
     * Populate a Column mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Column
     * @throws MappingException
     * @throws \Throwable
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Column
    {
        try {
            $this->annotationReader->setThrowExceptions($this->throwExceptions);
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $wasMapped);
            $objectiphyColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
            $wasMapped = $wasMapped || $objectiphyColumn;
            $hostClassName = $reflectionProperty->getDeclaringClass()->getName();
            $hostProperty = $reflectionProperty->getName();

            return $this->decorate($hostClassName, $hostProperty, $column, $objectiphyColumn);
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Column();
        }
    }

    /**
     * Populate a Relationship mapping class based on annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Relationship
     * @throws MappingException
     * @throws \Throwable
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Relationship
    {
        try {
            $this->annotationReader->setThrowExceptions($this->throwExceptions);
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $wasMapped);
            $objectiphyRelationship = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                Relationship::class
            );
            $wasMapped = $wasMapped || $objectiphyRelationship;
            $hostClassName = $reflectionProperty->getDeclaringClass()->getName();
            $hostProperty = $reflectionProperty->getName();

            return $this->decorate($hostClassName, $hostProperty, $relationship, $objectiphyRelationship);
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Relationship(Relationship::UNDEFINED);
        }
    }

    /**
     * Takes a mapping object (Table, Column, Relationship), and replaces property values with the properties of an
     * equivalent object, overriding the base implementation. If the decorator's annotation did not specify a value for
     * a property, the original value of the component is preserved.
     * @param string $hostClassName
     * @param string $hostProperty
     * @param object $component The object whose values may be overridden.
     * @param object|null $decorator The object which holds the values that take priority.
     * @return object
     */
    private function decorate(
        string $hostClassName, 
        string $hostProperty, 
        object $component, 
        ?object $decorator = null
    ): object {
        if ($decorator) {
            if (get_class($component) == get_class($decorator)) {
                $itemName = $hostProperty ? $itemName = 'p:' . $hostProperty : 'c';
                $attributesRead = $this->annotationReader->getAttributesRead($hostClassName, $itemName, get_class($decorator));
                foreach ($attributesRead as $property => $value) {
                    ObjectHelper::populateFromObject($decorator, $property, $component);
                }
            }
        }

        return $component;
    }
}
