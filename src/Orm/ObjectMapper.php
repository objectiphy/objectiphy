<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface as NSI;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\LateBinding;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * Loads mapping information from the supplied mapping provider (typically annotations, but the mapping information 
 * could come from anywhere as long as there is a provider for it).
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectMapper
{
    /* @var $mappingCollection MappingCollection[] */
    private array $mappingCollection;
    private MappingProviderInterface $mappingProvider;
    private bool $eagerLoadToOne;
    private bool $eagerLoadToMany;
    private bool $guessMappings;
    private NamingStrategyInterface $tableNamingStrategy;
    private NamingStrategyInterface $columnNamingStrategy;
    
    public function __construct(MappingProviderInterface $mappingProvider)
    {
        $this->mappingProvider = $mappingProvider;
    }

    public function setConfigOptions(
        bool $eagerLoadToOne,
        bool $eagerLoadToMany,
        bool $guessMappings,
        NamingStrategyInterface $tableNamingStrategy,
        NamingStrategyInterface $columnNamingStrategy
    ) {
        $this->eagerLoadToOne = $eagerLoadToOne;
        $this->eagerLoadToMany = $eagerLoadToMany;
        $this->guessMappings = $guessMappings;
        $this->tableNamingStrategy = $tableNamingStrategy;
        $this->columnNamingStrategy = $columnNamingStrategy;
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
            $this->populateMappingCollection($className);
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
    private function populateMappingCollection(string $topClassName, string $className = '', array $parentProperties = [])
    {
        // We have to do all the scalar properties on the parent object first, then go through the kids -
        // otherwise recursive mappings will be detected and stopped on the child instead of the parent.
        $className = $className ?: $topClassName;
        $reflectionClass = new \ReflectionClass($className);
        $this->populateScalarMappings($topClassName, $reflectionClass, $parentProperties);
        $this->populateRelationalMappings($topClassName, $reflectionClass, $parentProperties);
    }

    private function populateScalarMappings(string $topClassName, \ReflectionClass $reflectionClass, array $parentProperties)
    {
        $mappingCollection = $this->mappingCollection[$topClassName];
        $table = $this->getTableMapping($reflectionClass, true);
        if (count($parentProperties) == 0) {
            $mappingCollection->setPrimaryTableMapping($table);
        }
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnIsMapped = false;
            $relationshipIsMapped = false;
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $columnIsMapped);
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $relationshipIsMapped);
            $relationship->setConfigOptions($this->eagerLoadToOne, $this->eagerLoadToMany);
            if (($columnIsMapped || $relationshipIsMapped) && $column->name != 'IGNORE') {
                $propertyMapping = new PropertyMapping(
                    $reflectionClass->getName(),
                    $reflectionProperty->getName(),
                    $table,
                    $column,
                    $relationship,
                    $parentProperties
                );
                $mappingCollection->addMapping($propertyMapping);
                //Resolve name *after* adding to collection so that naming strategies have access to the collection.
                $this->resolveColumnName($propertyMapping);
            }
        }
    }

    private function populateRelationalMappings(string $topClassName, \ReflectionClass $reflectionClass, array $parentProperties)
    {
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
            $relationship->setConfigOptions($this->eagerLoadToOne, $this->eagerLoadToMany);
            if ($relationship->isDefined()) {
                $propertyName = $reflectionProperty->getName();
                $this->mapRelationship($topClassName, $propertyName, $relationship, $reflectionClass, $parentProperties);
            }
        }
    }

    private function mapRelationship(string $topClassName, string $propertyName, Relationship $relationship, \ReflectionClass $reflectionClass, array $parentProperties)
    {
        $mappingCollection = $this->mappingCollection[$topClassName];
        if ($relationship->isLateBound() || $mappingCollection->isRelationshipAlreadyMapped($parentProperties, $propertyName)) {
            //Go this far, but no further
            $propertyMapping = new PropertyMapping(
                $reflectionClass->getName(),
                $propertyName,
                $this->getTableMapping($reflectionClass, true),
                new Column(),
                $relationship,
                $parentProperties
            );
            $mappingCollection->addMapping($propertyMapping);
            if ($relationship->mappedBy) { //Well, ok, we have to go a little bit further
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $childReflectionProperty = $childReflectionClass->getProperty($relationship->mappedBy);
                $propertyMapping = new PropertyMapping(
                    $relationship->childClassName,
                    $relationship->mappedBy,
                    $this->getTableMapping($childReflectionClass, true),
                    new Column(),
                    $this->mappingProvider->getRelationshipMapping($childReflectionProperty),
                    array_merge($parentProperties, [$propertyName])
                );
                $mappingCollection->addMapping($propertyMapping);
            }
        } else { //if (!$mappingCollection->isRelationshipAlreadyMapped($parentProperties, $propertyName)) {
            $childParentProperties = array_merge($parentProperties, [$propertyName]);
            $this->populateMappingCollection($topClassName, $relationship->childClassName, $childParentProperties);
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
        if ($this->guessMappings && empty($table->name)) {
            $table->name = $this->tableNamingStrategy->convertName(
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
        $strategy = $this->columnNamingStrategy ?? null;

        if ($this->guessMappings && $strategy) {
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
