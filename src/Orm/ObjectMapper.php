<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Annotations\AnnotationReader;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\LateBinding;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\NamingStrategy\NameResolver;
use Objectiphy\Objectiphy\Query\CriteriaExpression;

/**
 * Loads mapping information from the supplied mapping provider (typically annotations, but the mapping information 
 * could come from anywhere as long as there is a provider for it).
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectMapper
{
    /* @var $mappingCollection MappingCollection[] */
    private array $mappingCollections;
    private MappingProviderInterface $mappingProvider;
    private bool $productionMode;
    private bool $eagerLoadToOne;
    private bool $eagerLoadToMany;
    private NameResolver $nameResolver;
    
    public function __construct(MappingProviderInterface $mappingProvider, NameResolver $nameResolver)
    {
        $this->mappingProvider = $mappingProvider;
        $this->nameResolver = $nameResolver;
    }

    public function setConfigOptions(
        bool $productionMode,
        bool $eagerLoadToOne,
        bool $eagerLoadToMany,
        bool $guessMappings,
        NamingStrategyInterface $tableNamingStrategy,
        NamingStrategyInterface $columnNamingStrategy
    ): void {
        $this->productionMode = $productionMode;
        $this->mappingProvider->setThrowExceptions(!$this->productionMode);
        $this->eagerLoadToOne = $eagerLoadToOne;
        $this->eagerLoadToMany = $eagerLoadToMany;
        $this->nameResolver->setConfigOptions($guessMappings, $tableNamingStrategy, $columnNamingStrategy);
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

        if (!isset($this->mappingCollections[$className])) {
            $mappingCollection = new MappingCollection($className);
            $this->mappingCollections[$className] = $mappingCollection;
            $this->populateMappingCollection($mappingCollection);
        }

        return $this->mappingCollections[$className];
    }

    /**
     * Depending on the criteria, we might need additional mappings - eg. to search on the value of
     * a late bound child object.
     * @param string $className Name of top-level class
     * @param array | null $criteria
     */
    public function addCriteriaMappings(string $className, ?array $criteria = null)
    {
        foreach ($criteria ?? [] as $criteriaExpression) {
            if ($criteriaExpression instanceof CriteriaExpression) {
                foreach($criteriaExpression->getPropertyPathsUsed() as $propertyPath) {
                    $this->addMappingForProperty($className, $propertyPath);
                }
            }
        }
    }

    private function addMappingForProperty(string $className, string $propertyPath)
    {
        $mappingCollection = $this->mappingCollections[$className];
        if (!$mappingCollection->getColumnForPropertyPath($propertyPath)) {
            $parts = explode('.', $propertyPath);
            $property = '';
            $parent = null;
            foreach ($parts as $part) {
                $property .= ((strlen($property) > 0) ? '.' : '') . $part;
                $existingParent = $mappingCollection->getPropertyMapping($property);
                if ($parent && $parent->getChildClassName() && !$existingParent) {
                    //Add to $parent
                    $reflectionProperty = new \ReflectionProperty($parent->getChildClassName(), $part);
                    $table = $this->getTableMapping(new \ReflectionClass($parent->getChildClassName()));
                    $parents = array_merge($parent->parents, [$parent->propertyName]);
                    //Mark it as early bound...
                    $parent->forceEarlyBindingForJoin(); //We need to join even if it is to-many, so we can filter
                    $this->mapProperty($mappingCollection, $reflectionProperty, $table, $parents, true);
                } else {
                    $parent = $existingParent;
                }
            }
        }
    }

    /**
     * Get mapping for class and loop through its properties to get their mappings too. Recursively populate mappings 
     * for child objects until we detect a loop or hit something that should be lazy loaded.
     * @throws \ReflectionException
     */
    private function populateMappingCollection(
        MappingCollection $mappingCollection,
        string $className = '',
        array $parents = []
    ): void {
        // We have to do all the scalar properties on the parent object first, then go through the kids -
        // otherwise recursive mappings will be detected and stopped on the child instead of the parent.
        $className = $className ?: $mappingCollection->getEntityClassName();
        $reflectionClass = new \ReflectionClass($className);
        if (!$parents) { //If a parent is present, we will already have done the scalar mappings
            $this->populateScalarMappings($mappingCollection, $reflectionClass, $parents);
        }
        $this->populateRelationalMappings($mappingCollection, $reflectionClass, $parents);
        $this->populateRelationalMappings($mappingCollection, $reflectionClass, $parents, true);
    }

    /**
     * @param MappingCollection $mappingCollection
     * @param \ReflectionClass $reflectionClass
     * @param array $parents
     * @param string $propertyName Restricts mapping to a single property (used for criteria joins)
     * @throws ObjectiphyException
     */
    private function populateScalarMappings(
        MappingCollection $mappingCollection,
        \ReflectionClass $reflectionClass,
        array $parents
    ): void {
        $table = $this->getTableMapping($reflectionClass, true);
        if (count($parents) == 0) {
            $mappingCollection->setPrimaryTableMapping($table);
        }
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $propertyMapping = $this->mapProperty($mappingCollection, $reflectionProperty, $table, $parents);
            if ($propertyMapping) {
                //For lazy loading, we must have the primary key so we can load the child
                if ($propertyMapping->relationship->isLateBound()
                    && !$propertyMapping->relationship->mappedBy
                    && !$propertyMapping->relationship->isEmbedded
                ) {
                    $childPks = $mappingCollection->getPrimaryKeyProperties($propertyMapping->getChildClassName());
                    if (empty($childPks)) {
                        $this->populatePrimaryKeyMappings($mappingCollection, $propertyMapping->getChildClassName());
                    }
                }
            }
        }
    }

    private function mapProperty(
        MappingCollection $mappingCollection,
        \ReflectionProperty $reflectionProperty,
        Table $table,
        array $parents,
        bool $suppressFetch = false
    ): ?PropertyMapping {
        $columnIsMapped = false;
        $relationshipIsMapped = false;
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $columnIsMapped);
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $relationshipIsMapped);
        $this->initialiseRelationship($relationship);
        if (($columnIsMapped || $relationshipIsMapped) && $column->name != 'IGNORE') {
            $childTable = null;
            if ($relationship->childClassName) {
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $childTable = $this->mappingProvider->getTableMapping($childReflectionClass);
            }
            $propertyMapping = new PropertyMapping(
                $reflectionProperty->getDeclaringClass()->getName(),
                $reflectionProperty,
                $table,
                $childTable,
                $column,
                $relationship,
                $parents
            );
            $mappingCollection->addMapping($propertyMapping, $suppressFetch);
            //Resolve name *after* adding to collection so that naming strategies have access to the collection.
            $this->nameResolver->resolveColumnName($propertyMapping);

            return $propertyMapping;
        }

        return null;
    }

    private function populatePrimaryKeyMappings(MappingCollection $mappingCollection, string $className): void
    {
        $reflectionClass = new \ReflectionClass($className);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty);
            if ($column->isPrimaryKey) {
                $mappingCollection->addPrimaryKeyMapping($reflectionClass->getName(), $reflectionProperty->getName(), $column);
            }
        }
    }

    private function populateRelationalMappings(
        MappingCollection $mappingCollection,
        \ReflectionClass $reflectionClass,
        array $parents,
        bool $drillDown = false
    ): void {
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
            $this->initialiseRelationship($relationship);
            if ($relationship->isDefined()) {
                if ($relationship->isEmbedded) {
                    continue; //Temporary measure until we support embedables.
                }
                if ($relationship->targetJoinColumn) {
                    $targetProperty = $this->findTargetProperty($relationship);
                    $relationship->setTargetProperty($targetProperty);
                }
                $propertyName = $reflectionProperty->getName();
                $this->mapRelationship($mappingCollection, $propertyName, $relationship, $reflectionClass, $parents, $drillDown);
            }
        }
    }

    private function mapRelationship(
        MappingCollection $mappingCollection,
        string $propertyName,
        Relationship $relationship,
        \ReflectionClass $reflectionClass,
        array $parents,
        bool $drillDown = false
    ): void {
        if ($relationship->isLateBound()
            || $mappingCollection->isRelationshipAlreadyMapped($parents, $propertyName, $reflectionClass->getName())
        ) {
            if ($relationship->mappedBy) { //Go this far, but no further
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $childReflectionProperty = $childReflectionClass->getProperty($relationship->mappedBy);
                $childRelationship = $this->mappingProvider->getRelationshipMapping($childReflectionProperty);
                $this->initialiseRelationship($childRelationship);
                $childTable = $this->getTableMapping($childReflectionClass, true);
                $propertyMapping = new PropertyMapping(
                    $relationship->childClassName,
                    $childReflectionProperty,
                    $childTable,
                    null,
                    new Column(),
                    $childRelationship,
                    array_merge($parents, [$propertyName])
                );
                $mappingCollection->addMapping($propertyMapping);
            } /*elseif ($relationship->targetJoinColumn && !$mappingCollection->getPropertyByColumn($relationship->targetJoinColumn, null, $relationship->childClassName, false)) {
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);

                $stop = true;
            }*/
        } else {
            $childParents = array_merge($parents, [$propertyName]);
            if (!$drillDown) {
                //Just do the scalar properties and return
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $this->populateScalarMappings($mappingCollection, $childReflectionClass, $childParents);
            } else {
                $mappingCollection->markRelationshipMapped($propertyName, $reflectionClass->getName(), $childParents);
                $this->populateMappingCollection($mappingCollection, $relationship->childClassName, $childParents);
            }
        }
    }

    private function initialiseRelationship(Relationship $relationship): void
    {
        $relationship->setConfigOptions($this->eagerLoadToOne, $this->eagerLoadToMany);
        if ($relationship->targetJoinColumn) {
            $targetProperty = $this->findTargetProperty($relationship);
            $relationship->setTargetProperty($targetProperty);
        }
    }

    /**
     * In case of lazy loading, we need to know which properties to use for the target, even if we don't map them.
     * @param Relationship $relationship
     * @return string|void
     * @throws \ReflectionException
     */
    private function findTargetProperty(Relationship $relationship)
    {
        $properties = [];
        $reflectionClass = new \ReflectionClass($relationship->childClassName);
        $targetColumns = explode(',', $relationship->targetJoinColumn);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnMapping = $this->mappingProvider->getColumnMapping($reflectionProperty);
            foreach ($targetColumns as $targetColumn) {
                if ($columnMapping->name == trim($targetColumn)) {
                    $properties[] = $reflectionProperty->getName();
                    break;
                }
            }
            if (count($properties) == count($targetColumns)) {
                break;
            }
        }

        return implode(',', $properties);
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
        $this->nameResolver->resolveTableName($reflectionClass, $table);
        
        return $table;
    }
}
