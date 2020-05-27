<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface as NSI;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\MappingCollection;
use Objectiphy\Objectiphy\Orm\ConfigOptions;

/**
 * Loads mapping information from the supplied mapping provider (typically annotations, but the mapping information 
 * could come from anywhere as long as there is a provider for it).
 */
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

    /**
     * Returns a collection of property mappings for the object hierarchy of the given parent class.
     * @throws \ReflectionException
     */
    public function getMappingCollectionForClass(string $className): MappingCollection
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
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty);
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
            if ($column->name != 'IGNORE' && ($column->name || $relationship->relationshipType)) {
                $propertyMapping = new PropertyMapping(
                    $className,
                    $reflectionProperty->getName(),
                    $table,
                    $column,
                    $relationship,
                    $parentProperties
                );
                $this->mappingCollection->addMapping($propertyMapping);
                $this->resolveColumnName($propertyMapping);
                if ($this->shouldAddChildMappings($propertyMapping)) {
                    $parentProperties[] = $reflectionProperty->getName();
                    $this->populateMappingCollection($relationship->childClassName, $parentProperties);
                }
            }
        }
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
        if ($relationship->childClassName ?? false && $relationship->isEager($this->config)) {
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
        if ($this->config->guessMappings && empty($table->name)) {
            $table->name = $this->config->tableNamingStrategy->convertName(
                $reflectionClass->getName(), 
                NSI::TYPE_CLASS
            );
        }
    }

    /**
     * If we have a column mapping but without a name, use naming strategy to convert property name, or if we have a 
     * relationship mapping but without a source column name (and without deferral of mapping to the other side of the 
     * relationship), use naming strategy to convert property name - but all that only if config says we should guess.
     * @param PropertyMapping $propertyMapping
     */
    private function resolveColumnName(PropertyMapping $propertyMapping)
    {
        //Local variables make the code that follows more readable
        $propertyName = $propertyMapping->propertyName;
        $parentClassName = $propertyMapping->className;
        $relationship = $propertyMapping->relationship;
        $column = $propertyMapping->column;
        $strategy = $this->config->columnNamingStrategy ?? null;

        if ($this->config->guessMappings && $strategy) {
            if (empty($column->name) && !$relationship->relationshipType) {
                //Resolve column name for scalar value property
                $column->name = $strategy->convertName(
                    $propertyName,
                    NSI::TYPE_SCALAR_PROPERTY,
                    $propertyMapping);
            } elseif ((!$relationship->sourceJoinColumn && !$relationship->mappedBy)) {
                //Resolve source join column name (foreign key) for relationship property
                $relationship->sourceJoinColumn = $strategy->convertName(
                    $propertyName,
                    NSI::TYPE_RELATIONSHIP_PROPERTY,
                    $propertyMapping
                );
            }
        }
    }
}
