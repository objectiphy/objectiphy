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

    protected array $tables = [];
    protected array $columns = [];
    protected array $relationships = [];
    protected array $groups = [];

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
            if (!isset($this->tables[$reflectionClass->getName()])) {
                $this->annotationReader->setThrowExceptions($this->throwExceptions);
                $table = $this->mappingProvider->getTableMapping($reflectionClass, $wasMapped);
                $objectiphyTable = $this->annotationReader->getClassAnnotation($reflectionClass, Table::class);
                $wasMapped = $wasMapped || $objectiphyTable;
                $hostClassName = $reflectionClass->getName();
                $hostProperty = '';
                $table = $this->decorate($hostClassName, $hostProperty, $table, $objectiphyTable);
                $this->tables[$reflectionClass->getName()] = ['table' => $table, 'wasMapped' => $wasMapped];
            }
            $wasMapped = $this->tables[$reflectionClass->getName()]['wasMapped'];

            return $this->tables[$reflectionClass->getName()]['table'];
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
            $columnKey = $reflectionProperty->getDeclaringClass()->getName() . ':' . $reflectionProperty->getName();
            if (!isset($this->columns[$columnKey])) {
                $this->annotationReader->setThrowExceptions($this->throwExceptions);
                $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $wasMapped);
                $objectiphyColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Column::class);
                $wasMapped = $wasMapped || $objectiphyColumn;
                $hostClassName = $reflectionProperty->getDeclaringClass()->getName();
                $hostProperty = $reflectionProperty->getName();
                $this->columns[$columnKey] = [
                    'column' => $this->decorate($hostClassName, $hostProperty, $column, $objectiphyColumn),
                    'wasMapped' => $wasMapped
                ];
            }
            $wasMapped = $this->columns[$columnKey]['wasMapped'];

            return $this->columns[$columnKey]['column'];
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
            $relationshipKey = $reflectionProperty->getDeclaringClass()->getName() . ':' . $reflectionProperty->getName();
            if (!isset($this->relationships[$relationshipKey])) {
                $this->annotationReader->setThrowExceptions($this->throwExceptions);
                $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $wasMapped);
                $objectiphyRelationship = $this->annotationReader->getPropertyAnnotation(
                    $reflectionProperty,
                    Relationship::class
                );
                $wasMapped = $wasMapped || $objectiphyRelationship;
                $hostClassName = $reflectionProperty->getDeclaringClass()->getName();
                $hostProperty = $reflectionProperty->getName();
                $this->relationships[$relationshipKey] = [
                    'relationship' => $this->decorate($hostClassName, $hostProperty, $relationship, $objectiphyRelationship),
                    'wasMapped' => $wasMapped
                ];
            }
            $wasMapped = $this->relationships[$relationshipKey]['wasMapped'];

            return $this->relationships[$relationshipKey]['relationship'];
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Relationship();
        }
    }

    /**
     * Get any serialization groups that the property belongs to, if applicable.
     */
    public function getSerializationGroups(\ReflectionProperty $reflectionProperty): array
    {
        try {
            $groupKey = $reflectionProperty->class . ':' . $reflectionProperty->getName();
            if (!isset($this->groups[$groupKey])) {
                $groups = [];
                $baseGroups = $this->mappingProvider->getSerializationGroups($reflectionProperty);
                $annotations = $this->annotationReader->getPropertyAnnotations($reflectionProperty);
                $getterName = 'get' . ucfirst($reflectionProperty->getName());
                if (method_exists($reflectionProperty->getDeclaringClass()->getName(), $getterName)) {
                    $reflectionMethod = new \ReflectionMethod(
                        $reflectionProperty->getDeclaringClass()->getName(),
                        $getterName
                    );
                    $methodAnnotations = $this->annotationReader->getMethodAnnotations($reflectionMethod);
                    $annotations = array_merge($annotations, $methodAnnotations);
                }
                foreach ($annotations as $annotation) {
                    if (method_exists($annotation, 'getGroups')) {
                        $groups = $annotation->getGroups();
                        break;
                    } elseif (property_exists($annotation, 'groups')) {
                        $groups = $annotation->groups;
                        break;
                    }
                }
                $this->groups[$groupKey] = array_unique(array_merge($baseGroups, $groups));
            }

            return $this->groups[$groupKey];
        } catch (\Throwable $ex) {
            return [];
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
