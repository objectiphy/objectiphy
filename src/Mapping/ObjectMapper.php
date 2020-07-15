<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface as NSI;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Orm\ConfigOptions;

/**
 * Loads mapping information from the supplied mapping provider (typically annotations, but the mapping information 
 * could come from anywhere as long as there is a provider for it).
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class ObjectMapper
{
    /* @var $mappingCollection MappingCollection[] */
    private array $mappingCollection;
    private MappingProviderInterface $mappingProvider;
    private ConfigOptions $config;
    
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
        if (!$className) {
            throw new ObjectiphyException('Cannot get mapping information as no entity class name has been specified. Please call setClassName before attempting to load or save any data.');
        }

        if (!isset($this->mappingCollection[$className])) {
            $this->mappingCollection[$className] = new MappingCollection($className);
            $this->populateMappingCollection($this->mappingCollection[$className], $className);
        }

        return $this->mappingCollection[$className];
    }

    /**
     * Get mapping for class and loop through its properties to get their mappings too. Recursively populate mappings 
     * for child objects until we detect a loop or hit something that should be lazy loaded.
     * @param string $className
     * @param array $parentProperties
     * @throws \ReflectionException
     */
    private function populateMappingCollection(MappingCollection $mappingCollection, string $className, array $parentProperties = [])
    {
        $reflectionClass = new \ReflectionClass($className);
        $table = $this->getTableMapping($reflectionClass, true);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnIsMapped = false;
            $relationshipIsMapped = false;
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $columnIsMapped);
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $relationshipIsMapped);
            if (($columnIsMapped || $relationshipIsMapped) && $column->name != 'IGNORE') {
                $propertyMapping = new PropertyMapping(
                    $className,
                    $reflectionProperty->getName(),
                    $table,
                    $column,
                    $relationship,
                    $parentProperties
                );
                //Check this before adding, otherwise it will always be true!
                $childrenAlreadyMapped = $mappingCollection->isRelationshipMapped($propertyMapping, $this->config);
                $mappingCollection->addMapping($propertyMapping);
                //Resolve name *after* adding to collection so that naming strategies have access to the collection.
                $this->resolveColumnName($propertyMapping);
                if ($childrenAlreadyMapped) {
                    $childParentProperties = array_merge($parentProperties, [$reflectionProperty->getName()]);
                    $this->populateMappingCollection($mappingCollection, $relationship->childClassName, $childParentProperties);
                }
            }
        }
    }

    /**
     * Get the table mapping for the parent entity.
     * @param \ReflectionClass $reflectionClass
     * @param bool $exceptionIfUnmapped Whether or not to throw an exception if table mapping not found (parent only).
     * @return Table
     * @throws ObjectiphyException
     */
    private function getTableMapping(\ReflectionClass $reflectionClass, bool $exceptionIfUnmapped = false): Table
    {
        $tableIsMapped = false;
        $table = $this->mappingProvider->getTableMapping($reflectionClass, $tableIsMapped);
        if ($exceptionIfUnmapped && !$tableIsMapped) {
            $message = 'Cannot populate mapping collection for class %1$s as there is no table mapping specified. Did you forget to add a Table annotation to your entity class?';
            throw new ObjectiphyException(sprintf($message, $reflectionClass->getName()));
        }
        $this->resolveTableName($reflectionClass, $table);
        
        return $table;
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
            if (empty($column->name) && !$relationship->isDefined()) {
                //Resolve column name for scalar value property
                $column->name = $strategy->convertName(
                    $propertyName,
                    NSI::TYPE_SCALAR_PROPERTY,
                    $propertyMapping);
            } elseif ($relationship->isDefined() && (!$relationship->sourceJoinColumn && !$relationship->mappedBy)) {
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
