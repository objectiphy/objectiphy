<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\EntityProxyInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\NamingStrategy\NameResolver;
use Psr\SimpleCache\CacheInterface;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Loads mapping information from the supplied mapping provider (typically annotations, but the mapping information
 * could come from anywhere as long as there is a provider for it).
 */
final class ObjectMapper
{
    /**
     * @var MappingCollection[] $mappingCollection
     */
    private array $mappingCollections;
    private MappingProviderInterface $mappingProvider;
    private ?bool $eagerLoadToOne = null;
    private bool $eagerLoadToMany;
    private bool $guessMappings;
    /**
     * @var ConfigEntity[]
     */
    private array $entityConfig = [];
    private NameResolver $nameResolver;
    private int $maxDepth;
    private int $currentDepth = 0;
    private string $defaultCollectionClass = '';
    private string $configHash = '';
    private ?CacheInterface $cache = null;
    private array $ignoredProperties = [];

    public function __construct(
        MappingProviderInterface $mappingProvider,
        NameResolver $nameResolver,
        ?CacheInterface $cache = null
    ) {
        $this->mappingProvider = $mappingProvider;
        $this->nameResolver = $nameResolver;
        $this->cache = $cache;
    }

    /**
     * @param ConfigOptions $config
     * @throws ObjectiphyException
     */
    public function setConfigOptions(ConfigOptions $config): void
    {
        $this->mappingProvider->setThrowExceptions($config->devMode);
        $this->guessMappings = $config->guessMappings;
        $this->eagerLoadToOne = $config->eagerLoadToOne;
        $this->eagerLoadToMany = $config->eagerLoadToMany;
        $this->maxDepth = $config->maxDepth;
        $this->defaultCollectionClass = $config->defaultCollectionClass;
        $this->nameResolver->setConfigOptions($config);
        $this->entityConfig = $config->getConfigOption(ConfigOptions::ENTITY_CONFIG);
        $this->configHash = $config->getHash();
    }

    /**
     * Remove any mapping information for the given class, and any classes that have a property that uses it.
     * @param string|null $className
     */
    public function clearMappingCache(?string $className = null)
    {
        if ($className) {
            $unsets = [];
            foreach ($this->mappingCollections ?? [] as $class => $mappingCollection) {
                if ($mappingCollection->usesClass($className)) {
                    $unsets[] = $class;
                }
            }
            foreach ($unsets as $unset) {
                if ($this->cache) {
                    $this->cache->delete($unset);
                }
                unset($this->mappingCollections[$unset]);
            }
        } else {
            if ($this->cache) {
                $this->cache->clear();
            }
            $this->mappingCollections = [];
        }
    }

    /**
     * Returns a collection of property mappings for the object hierarchy of the given parent class.
     * @param string $className
     * @return MappingCollection
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function getMappingCollectionForClass(string $className): MappingCollection
    {
        if (!$className) {
            throw new ObjectiphyException('Cannot get mapping information as no entity class name has been specified. Please call setClassName before attempting to load or save any data.');
        }

        if (is_a($className, EntityProxyInterface::class, true)
            || is_a($className, ObjectReferenceInterface::class, true)
        ) {
            $className = get_parent_class($className);
        }
        $cacheKey = 'mc' . sha1($this->configHash . '_' . $className);

        if (!isset($this->mappingCollections[$cacheKey])) {
            $mappingCollection = null;
            if ($this->cache) {
                //Load from cache
                $mappingCollection = $this->cache->get($cacheKey);
                if (!$mappingCollection) {
                    //Not found? create it and save to cache
                    $mappingCollection = $this->createMappingCollection($className);
                    $this->cache->set($cacheKey, $mappingCollection);
                }
            } else {
                $mappingCollection = $this->createMappingCollection($className);
            }
            //Either way, save to in memory cache
            $this->mappingCollections[$cacheKey] = $mappingCollection;
        }

        return $this->mappingCollections[$cacheKey];
    }

    private function createMappingCollection(string $className): MappingCollection
    {
        $mappingCollection = new MappingCollection($className, $this->maxDepth);
        $this->currentDepth = 0;
        $this->populateMappingCollection($mappingCollection);
        $mappingCollection->getRelationships(); //Ensure all mapping information is populated

        return $mappingCollection;
    }

    /**
     * Depending on the criteria, we might need additional mappings - eg. to search on the value of
     * a late bound child object.
     * @param string $className Name of top-level class
     * @param PropertyPathConsumerInterface|null $pathConsumer
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function addExtraMappings(string $className, PropertyPathConsumerInterface $pathConsumer = null): bool
    {
        $extraMappingsAdded = false;
        if ($pathConsumer) {
            foreach ($pathConsumer->getPropertyPaths() ?? [] as $propertyPath) {
                $this->addMappingForProperty($className, $propertyPath, true);
                $extraMappingsAdded = true;
            }
        }

        return $extraMappingsAdded;
    }

    public function addExtraClassMappings(string $parentClass, QueryInterface $query)
    {
        $mappingCollection = $this->getMappingCollectionForClass($parentClass);
        $queryClasses = $query->getClassesUsed();
        foreach ($queryClasses as $queryClass) {
            if (!$mappingCollection->usesClass($queryClass) && class_exists($queryClass)) {
                $reflectionClass = new \ReflectionClass($queryClass);
                $table = $this->getTableMapping($reflectionClass);
                $mappingCollection->addExtraTableMapping($queryClass, $table);
            }
        }
    }

    /**
     * Add a property that would not normally need to be mapped, but is required for the criteria to filter on.
     * If there are any parent properties in between the deepest one we have already mapped, and the one we
     * want, we will have to add those too.
     * @param string $className
     * @param string $propertyPath
     * @param bool $forceJoins
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    public function addMappingForProperty(string $className, string $propertyPath, bool $forceJoins = false): void
    {
        $mappingCollection = $this->getMappingCollectionForClass($className);
        if (!$mappingCollection->getColumnForPropertyPath($propertyPath) || $forceJoins) {
            $parts = explode('.', $propertyPath);
            $property = '';
            $parent = null;
            foreach ($parts as $index => $part) {
                $property .= ((strlen($property) > 0) ? '.' : '') . $part;
                $existingParent = $mappingCollection->getPropertyMapping($property);
                if ($parent && $parent->getChildClassName() && !$existingParent) {
                    //Add to $parent
                    $reflectionClass = new \ReflectionClass($parent->getChildClassName());
                    $reflectionProperty = new \ReflectionProperty($reflectionClass->getName(), $part);
                    $table = $this->getTableMapping($reflectionClass);
                    $parents = array_merge($parent->parents, [$parent->propertyName]);
                    //Mark it as early bound...
                    $parent = $this->mapProperty($mappingCollection, $reflectionClass, $reflectionProperty, $table, $parents, $parent->relationship, true);
                    if (!$parent) {
                        throw new MappingException('No relationship has been defined for ' . $reflectionClass->getName() . '::' . $reflectionProperty->getName() . ', but this relationship is required by the query being executed.');
                    }
                    $parent->forceEarlyBindingForJoin(); //We need to join even if it is to-many, so we can filter
                    if (!$parent->relationship->sourceJoinColumn && $parent->relationship->mappedBy) {
                        //If mapping is on the other side, we have to get that too
                        $additional = $parent->getPropertyPath() . '.' . $parent->relationship->mappedBy;
                        $this->addMappingForProperty($className, $additional, $forceJoins);
                    }
                } else {
                    $parent = $existingParent;
                    if ($parent && $forceJoins) {
                        $parent->forceEarlyBindingForJoin();
                    }
                }
            }
        }
    }

    /**
     * Get the table mapping for the parent entity and apply any overrides if applicable.
     * @param \ReflectionClass $reflectionClass
     * @param bool $exceptionIfUnmapped Whether or not to throw an exception if table mapping not found (parent only).
     * @param bool $tableIsMapped
     * @return Table
     * @throws ObjectiphyException
     */
    public function getTableMapping(
        \ReflectionClass $reflectionClass,
        bool $exceptionIfUnmapped = false,
        bool &$tableIsMapped = false
    ): Table {
        $table = $this->mappingProvider->getTableMapping($reflectionClass, $tableIsMapped);
        $entityConfig = $this->entityConfig[$reflectionClass->getName()] ?? null;
        if ($entityConfig) {
            $overrides = $entityConfig->getConfigOption(ConfigEntity::TABLE_OVERRIDES);
            if (!empty($overrides)) {
                if (!is_array($overrides)) {
                    throw new ObjectiphyException(
                        'You have attempted to override a table mapping but have not supplied all of the necessary information. Format is [$attribute => $value].'
                    );
                }

                foreach ($overrides ?? [] as $overrideKey => $overrideValue) {
                    if (property_exists($table, $overrideKey)) {
                        $table->$overrideKey = $overrideValue;
                        $tableIsMapped = true;
                    }
                }
            }
        }
        $this->nameResolver->resolveTableName($reflectionClass, $table);

        //If nothing on this class, check the inheritance hierarchy
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass && !$tableIsMapped) {
            //Throw exceptions from outer method, so the error message includes the correct concrete class name
            $table = $this->getTableMapping($parentClass, false, $tableIsMapped);
        }

        if ($exceptionIfUnmapped && !$tableIsMapped) {
            $message = 'Cannot populate mapping collection for class %1$s as there is no table mapping specified. Did you forget to add a Table annotation to your entity class?';
            $table = $this->mappingProvider->getTableMapping($reflectionClass, $tableIsMapped);
            throw new ObjectiphyException(sprintf($message, $reflectionClass->getName()));
        }

        return $table;
    }

    /**
     * Load mapping information, and apply any overrides, if applicable.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $relationshipIsMapped
     * @return Relationship
     * @throws ObjectiphyException
     */
    private function getRelationshipMapping(
        \ReflectionClass $reflectionClass,
        \ReflectionProperty $reflectionProperty,
        bool &$relationshipIsMapped = false
    ): Relationship {
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $relationshipIsMapped);
        $entityConfig = $this->entityConfig[$reflectionClass->getName()] ?? null;
        if ($entityConfig) {
            $overrides = $entityConfig->getConfigOption(ConfigEntity::RELATIONSHIP_OVERRIDES);
        }
        if (!empty($overrides)) {
            if (!is_array($overrides) || !is_array(reset($overrides))) {
                throw new ObjectiphyException(
                    'You have attempted to override a relationship mapping but have not supplied all of the necessary information. Format is [$propertyName => [$attribute => $value]].'
                );
            }
            foreach ($overrides[$reflectionProperty->getName()] ?? [] as $overrideKey => $overrideValue) {
                if (property_exists($relationship, $overrideKey)) {
                    $relationship->$overrideKey = $overrideValue;
                    $relationshipIsMapped = true;
                }
            }
        }
        if ($relationship->isDefined() && !$relationship->childClassName && !$relationship->isEmbedded && !$relationship->isScalarJoin()) {
            $errorMessage = sprintf('Relationship defined without a child class name for property \'%1$s\' on class \'%2$s\'. Please specify a value for the childClassName attribute.', $reflectionProperty->getName(), $reflectionProperty->getDeclaringClass()->getName());
            throw new MappingException($errorMessage);
        }

        return $relationship;
    }

    /**
     * Load mapping information, and apply any overrides, if applicable.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $columnIsMapped
     * @return Column
     * @throws ObjectiphyException
     */
    private function getColumnMapping(
        \ReflectionClass $reflectionClass,
        \ReflectionProperty $reflectionProperty,
        bool &$columnIsMapped = false
    ): Column {
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $columnIsMapped);
        $entityConfig = $this->entityConfig[$reflectionClass->getName()] ?? null;
        if ($entityConfig) {
            $overrides = $entityConfig->getConfigOption(ConfigEntity::COLUMN_OVERRIDES);
        }
        if (!empty($overrides)) {
            $errorPrefix = 'You have attempted to override a column mapping for ' . $reflectionClass->getName() . '::' . $reflectionProperty->getName();
            if (!is_array($overrides) || !is_array(reset($overrides))) {
                throw new MappingException(
                    $errorPrefix . ' but have not supplied all of the necessary information. Format is [$propertyName => [$attribute => $value]].'
                );
            }
            if (!is_array($overrides[$reflectionProperty->getName()] ?? [])) {
                throw new MappingException(
                    $errorPrefix . ' but the value supplied is not an array, which it should be! Format is [$propertyName => [$attribute => $value]].'
                );
            }
            foreach ($overrides[$reflectionProperty->getName()] ?? [] as $overrideKey => $overrideValue) {
                if (property_exists($column, $overrideKey)) {
                    $column->$overrideKey = $overrideValue;
                    $columnIsMapped = true;
                }
            }
        }
        if ($column->aggregateFunctionName) {
            $column->isReadOnly = true;
        }

        return $column;
    }

    /**
     * Get mapping for class and loop through its properties to get their mappings too. Recursively populate mappings
     * for child objects until we detect a loop or hit something that should be lazy loaded.
     * @param MappingCollection $mappingCollection
     * @param string $className
     * @param array $parents
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function populateMappingCollection(
        MappingCollection $mappingCollection,
        string $className = '',
        array $parents = []
    ): void {
        // We have to do all the scalar properties on the parent object first, then go through the kids -
        // otherwise recursive mappings will be detected and stopped on the child instead of the parent.
        $this->currentDepth++;
        $className = $className ?: $mappingCollection->getEntityClassName();
        $reflectionClass = new \ReflectionClass($className);
        if (!$parents) { //If a parent is present, we will already have done the scalar mappings
            $this->populateScalarMappings($mappingCollection, $reflectionClass, $parents);
        }
        $this->populateRelationalMappings($mappingCollection, $reflectionClass, $parents);
        $this->populateRelationalMappings($mappingCollection, $reflectionClass, $parents, true);
        $this->currentDepth--;
    }

    /**
     * @param MappingCollection $mappingCollection
     * @param \ReflectionClass $reflectionClass
     * @param array $parents
     * @param Relationship|null $parentRelationship
     * @throws ObjectiphyException|MappingException|\ReflectionException
     */
    private function populateScalarMappings(
        MappingCollection $mappingCollection,
        \ReflectionClass $reflectionClass,
        array $parents,
        Relationship $parentRelationship = null
    ): void {
        $table = $parents ?
            $mappingCollection->getPropertyMapping(implode('.', $parents))->childTable :
            $this->getTableMapping($reflectionClass, true);
        if (count($parents) == 0) {
            $mappingCollection->setPrimaryTableMapping($table);
        }

        $this->ignoredProperties = [];
        $classes = $this->getReflectionClassHierarchy($reflectionClass);
        foreach ($classes as $reflectionClass) {
            foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                if (!in_array($reflectionProperty->getName(), $this->ignoredProperties)) {
                    $propertyMapping = $this->mapProperty($mappingCollection, $reflectionClass, $reflectionProperty, $table, $parents, $parentRelationship);
                    if ($propertyMapping && $propertyMapping->relationship->isDefined()) {
                        if ($propertyMapping->relationship->isEmbedded || $propertyMapping->relationship->isScalarJoin()) {
                            $childParents = array_merge($parents, [$propertyMapping->propertyName]);
                            if ($propertyMapping->relationship->isEmbedded) {
                                $childReflectionClass = new \ReflectionClass($propertyMapping->getChildClassName());
                                $this->populateScalarMappings($mappingCollection, $childReflectionClass, $childParents, $propertyMapping->relationship);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Create a property mapping and add it to the collection.
     * @param MappingCollection $mappingCollection
     * @param \ReflectionProperty $reflectionProperty
     * @param Table $table
     * @param array $parents
     * @param Relationship|null $parentRelationship
     * @param bool $suppressFetch
     * @return PropertyMapping|null
     * @throws \ReflectionException|ObjectiphyException
     */
    private function mapProperty(
        MappingCollection $mappingCollection,
        \ReflectionClass $reflectionClass,
        \ReflectionProperty $reflectionProperty,
        Table $table,
        array $parents,
        Relationship $parentRelationship = null,
        bool $suppressFetch = false
    ): ?PropertyMapping {
        $propertyPath = ltrim(implode('.', $parents) . '.' . $reflectionProperty->getName(), '.');
        $existingPropertyMapping = $mappingCollection->getPropertyMapping($propertyPath);
        if ($existingPropertyMapping) {
            return $existingPropertyMapping;
        }

        $columnIsMapped = false;
        $relationshipIsMapped = false;
        $relationship = $this->getRelationshipMapping($reflectionClass, $reflectionProperty, $relationshipIsMapped);
        $column = $this->getColumnMapping($reflectionClass, $reflectionProperty, $columnIsMapped);
        $groups = $this->mappingProvider->getSerializationGroups($reflectionProperty);
        if ($parentRelationship && $parentRelationship->isEmbedded) {
            $column = clone($column);
            $column->name = $parentRelationship->embeddedColumnPrefix . $column->name;
            $relationship = clone($relationship);
            if ($relationship->sourceJoinColumn) {
                $relationship->sourceJoinColumn = $parentRelationship->embeddedColumnPrefix . $relationship->sourceJoinColumn;
            }
        }
        if ($relationship->isScalarJoin()) {
            $column->name = $relationship->targetScalarValueColumn;
        }
        $this->initialiseRelationship($relationship);
        if ($column->name == 'IGNORE') {
            $this->ignoredProperties[] = $reflectionProperty->getName();
        }
        if ((($columnIsMapped || $relationshipIsMapped) && $column->name != 'IGNORE')) {
            $childTable = null;
            if ($relationship->childClassName) {
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $childTable = $relationship->isEmbedded ? $table : null;
                $childTable = $childTable ?? $this->getTableMapping($childReflectionClass);
            }
            $propertyMapping = new PropertyMapping(
                $reflectionClass->getName(),
                $reflectionProperty,
                $table,
                $childTable,
                $column,
                $relationship,
                $parents,
                $groups
            );
            $mappingCollection->addMapping($propertyMapping, $suppressFetch);
            //Resolve name *after* adding to collection so that naming strategies have access to the collection.
            $this->nameResolver->resolveColumnName($propertyMapping);

            return $propertyMapping;
        }

        return null;
    }

    /**
     * Add minimal information about any primary keys
     * @param MappingCollection $mappingCollection
     * @param string $className
     * @throws \ReflectionException
     */
    private function populatePrimaryKeyMappings(MappingCollection $mappingCollection, string $className): void
    {
        $reflectionClass = new \ReflectionClass($className);
        $classes = $this->getReflectionClassHierarchy($reflectionClass);
        foreach ($classes as $innerReflectionClass) {
            foreach ($innerReflectionClass->getProperties() as $reflectionProperty) {
                $column = $this->getColumnMapping($reflectionClass, $reflectionProperty);
                if ($column->isPrimaryKey) {
                    $mappingCollection->addPrimaryKeyMapping($innerReflectionClass->getName(), $reflectionProperty->getName());
                }
            }
        }
    }

    private function getReflectionClassHierarchy(\ReflectionClass $reflectionClass)
    {
        $classes = [];
        while ($reflectionClass) {
            $classes[] = $reflectionClass;
            $reflectionClass = $reflectionClass->getParentClass();
        }

        return $classes;
    }

    /**
     * Loop through relationships and map them.
     * @param MappingCollection $mappingCollection
     * @param \ReflectionClass $reflectionClass
     * @param array $parents
     * @param bool $drillDown
     * @throws \ReflectionException|ObjectiphyException
     */
    private function populateRelationalMappings(
        MappingCollection $mappingCollection,
        \ReflectionClass $reflectionClass,
        array $parents,
        bool $drillDown = false
    ): void {
        $classes = $this->getReflectionClassHierarchy($reflectionClass);
        foreach ($classes as $innerReflectionClass) {
            foreach ($innerReflectionClass->getProperties() as $reflectionProperty) {
                $relationship = $this->getRelationshipMapping($reflectionClass, $reflectionProperty);
                if ($relationship->isDefined()) {
                    $this->initialiseRelationship($relationship);
                    $propertyName = $reflectionProperty->getName();
                    $this->mapRelationship($mappingCollection, $propertyName, $relationship, $innerReflectionClass, $parents, $drillDown);
                } elseif ($relationship->childClassName || $relationship->sourceJoinColumn || $relationship->bridgeJoinTable) {
                    throw new MappingException(sprintf('Relationship mapping information has been provided for %1$s but no relationshipType has been set.', $innerReflectionClass->getName() . '::' . $reflectionProperty->getName()));
                }
            }
        }
    }

    /**
     * Map relationship properties, avoiding infinite recursion, and only if needed (early bound).
     * @param MappingCollection $mappingCollection
     * @param string $propertyName
     * @param Relationship $relationship
     * @param \ReflectionClass $reflectionClass
     * @param array $parents
     * @param bool $drillDown
     * @throws ObjectiphyException
     * @throws \ReflectionException
     */
    private function mapRelationship(
        MappingCollection $mappingCollection,
        string $propertyName,
        Relationship $relationship,
        \ReflectionClass $reflectionClass,
        array $parents,
        bool $drillDown = false
    ): void {
        if (($this->maxDepth && count($parents) >= $this->maxDepth - 1)
            || $relationship->isLateBound($mappingCollection->getTableForClass($relationship->childClassName))
            || $mappingCollection->isRelationshipAlreadyMapped($parents, $propertyName, $reflectionClass->getName())
        ) {
            if ($relationship->mappedBy) { //Go this far, but no further
                if (!class_exists($relationship->childClassName)) {
                    $errorMessage = 'Specified child class (\'%1$s\') does not exist on relationship for property \'%2$s\' of class \'%3$s\'';
                    throw new MappingException(sprintf($errorMessage, $relationship->childClassName, $propertyName, $reflectionClass->getName()));
                }
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $childReflectionProperty = $childReflectionClass->getProperty($relationship->mappedBy);
                $childRelationship = $this->getRelationshipMapping($childReflectionClass, $childReflectionProperty);
                $childGroups = $this->mappingProvider->getSerializationGroups($childReflectionProperty);
                $this->initialiseRelationship($childRelationship);

                $grandchildTable = null;
                if ($childRelationship->childClassName) {
                    //We need the grandchild table too in case of joins
                    $grandchildReflectionClass = new \ReflectionClass(($childRelationship->childClassName));
                    $grandchildTable = $this->getTableMapping($grandchildReflectionClass) ?? null;
                }

                $childTable = $this->getTableMapping($childReflectionClass, true);
                $propertyMapping = new PropertyMapping(
                    $relationship->childClassName,
                    $childReflectionProperty,
                    $childTable,
                    $grandchildTable,
                    new Column(),
                    $childRelationship,
                    array_merge($parents, [$propertyName]),
                    $childGroups
                );
                $mappingCollection->addMapping($propertyMapping);
                $this->nameResolver->resolveColumnName($propertyMapping); //Resolve join columns
            } elseif (!$relationship->targetJoinColumn) {
                //For lazy loading, we must have the primary key so we can load the child
                $childPks = $mappingCollection->getPrimaryKeyProperties($relationship->childClassName);
                if (empty($childPks) && $relationship->childClassName) {
                    $this->populatePrimaryKeyMappings(
                        $mappingCollection,
                        $relationship->childClassName
                    );
                }
            }
        } else {
            $childParents = array_merge($parents, [$propertyName]);
            if (!$drillDown || ($this->maxDepth && $this->currentDepth >= $this->maxDepth)) {
                //Just do the scalar properties and return
                $childReflectionClass = new \ReflectionClass($relationship->childClassName);
                $this->populateScalarMappings($mappingCollection, $childReflectionClass, $childParents, $relationship);
            } else {
                $mappingCollection->markRelationshipMapped($propertyName, $reflectionClass->getName());
                $this->populateMappingCollection($mappingCollection, $relationship->childClassName, $childParents);
            }
        }
    }

    /**
     * Set config and if we use a non-standard column to join on, work out the equivalent property.
     * @param Relationship $relationship
     * @throws \ReflectionException
     */
    private function initialiseRelationship(Relationship $relationship): void
    {
        $relationship->setConfigOptions($this->eagerLoadToOne, $this->eagerLoadToMany);
        if ($relationship->childClassName && $relationship->targetJoinColumn) {
            $targetProperty = $this->findTargetProperty($relationship);
            $relationship->setTargetProperty($targetProperty);
        }
        if ($relationship->isToMany()) {
            $entityCollectionClass = $this->entityConfig[$relationship->childClassName]->collectionClass ?? '';
            $globalCollectionClass = $this->defaultCollectionClass;
            $relationship->collectionClass = $relationship->collectionClass ?: ($entityCollectionClass ?: $globalCollectionClass);
        }
        if ($this->guessMappings
            && $relationship->isDefined()
            && !$relationship->mappedBy
            && !$relationship->sourceJoinColumn
        ) {
            //For now, just add a dummy source column - it will be replaced by a resolved column name based on the
            //naming strategy later, but we need to know that the source column exists.
            $relationship->sourceJoinColumn = '[' . uniqid() . ']';
        }
    }

    /**
     * In case of lazy loading, we need to know which properties to use for the target, even if we don't map them.
     * @param Relationship $relationship
     * @return string
     * @throws \ReflectionException
     */
    private function findTargetProperty(Relationship $relationship): string
    {
        $properties = [];
        $reflectionClass = new \ReflectionClass($relationship->childClassName);
        $targetColumns = explode(',', $relationship->targetJoinColumn);
        $classes = $this->getReflectionClassHierarchy($reflectionClass);
        foreach ($classes as $innerReflectionClass) {
            foreach ($innerReflectionClass->getProperties() as $reflectionProperty) {
                $columnMapping = $this->getColumnMapping($reflectionClass, $reflectionProperty);
                //If name not specified, guess mapping if applicable...
                $columnMapping->name = $columnMapping->name ?: $this->nameResolver->convertName($reflectionProperty->getName(), NamingStrategyInterface::TYPE_SCALAR_PROPERTY);
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
        }

        return implode(',', array_unique($properties));
    }
}
