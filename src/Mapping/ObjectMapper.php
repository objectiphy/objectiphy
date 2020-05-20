<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
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
    private NamingStrategyInterface $namingStrategy;
    
    public function __construct(
        MappingProviderInterface $mappingProvider, 
        ConfigOptions $config,
        NamingStrategyInterface $namingStrategy
    ) {
        $this->mappingProvider = $mappingProvider;
        $this->config = $config;
        $this->namingStrategy = $namingStrategy;
    }

    public function getMappingCollectionForClass(string $className)
    {
        $this->mappingCollection = new MappingCollection($className);
        $this->populateMappingCollection($className);
        
        return $this->mappingCollection;
    }

    /**
     * Get mapping for class and loop through its properties to get their mappings too. Recursively populate mappings 
     * for child objects until we detect a loop or hit something that should be lazy loaded.
     * @param string $className
     * @param array $parentProperties
     * @throws \ReflectionException
     */
    private function populateMappingCollection(string $className, array $parentProperties = [])
    {
        $reflectionClass = new \ReflectionClass($className);
        $table = $this->mappingProvider->getTableMapping($reflectionClass);
        $this->resolveTableName($reflectionClass, $table);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $column = $this->getColumn($reflectionProperty);
            $relationship = $this->getRelationship($reflectionProperty);
            $this->resolveColumnName($reflectionProperty, $column, $relationship);
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

    private function getColumn(\ReflectionProperty $reflectionProperty)
    {
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty);
        if ($column) {
            $column->populateDefaultValues(); //Use defaults for anything we could not get mapping information for
        }

        return $column;
    }

    private function getRelationship(\ReflectionProperty $reflectionProperty)
    {
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
        if ($relationship) {
            $relationship->populateDefaultValues(); //Use defaults for anything we could not get mapping information for
        }

        return $relationship;
    }

    /**
     * Detect infinite recursion or lazy loading.
     * @param PropertyMapping $propertyMapping
     * @return bool
     */
    private function shouldAddChildMappings(PropertyMapping $propertyMapping)
    {
        $result = false;
        $relationship = $propertyMapping->relationship;
        $parentProperty = end($propertyMapping->parentProperties);
        if ($relationship->childClass ?? false && $relationship->isEager($this->config)) {
            $result = !$this->mappingCollection->isRelationshipMapped(
                $parentProperty, 
                $propertyMapping->className, 
                $propertyMapping->propertyName
            );
        }
        
        return $result;
    }

    /**
     * If we still don't know the table name, use naming strategy to convert class name
     * @param \ReflectionClass $reflectionClass
     * @param Table $table
     */
    private function resolveTableName(\ReflectionClass $reflectionClass, Table $table)
    {
        
    }

    /**
     * If we have a column mapping but without a name, use naming strategy to convert property name, or if we have a 
     * relationship mapping but without a source or target column name (and withou deferral of mapping to the other side 
     * of the relationship), use naming strategy to convert property name.
     * @param \ReflectionProperty $reflectionProperty
     * @param Column $column
     * @param Relationship $relationship
     */
    private function resolveColumnName(\ReflectionProperty $reflectionProperty, Column $column, Relationship $relationship)
    {
        //If name is IGNORE, we need to remove the mapping completely
        
    }
}
