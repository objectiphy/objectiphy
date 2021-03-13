<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Grab values off an object and plonk them in an array ready to be applied to a database query.
 */
final class ObjectUnbinder
{
    private MappingCollection $mappingCollection;
    private EntityTracker $entityTracker;
    private DataTypeHandlerInterface $dataTypeHandler;
    private ObjectMapper $objectMapper;
    private bool $disableDeleteRelationships = false;

    public function __construct(
        EntityTracker $entityTracker,
        DataTypeHandlerInterface $dataTypeHandler,
        ObjectMapper $objectMapper
    ) {
        $this->entityTracker = $entityTracker;
        $this->dataTypeHandler = $dataTypeHandler;
        $this->objectMapper = $objectMapper;
    }

    /**
     * @param MappingCollection $mappingCollection
     */
    public function setMappingCollection(MappingCollection $mappingCollection): void
    {
        $this->mappingCollection = $mappingCollection;
    }

    /**
     * @param ConfigOptions $config
     */
    public function setConfigOptions(ConfigOptions $config): void
    {
        $this->disableDeleteRelationships = $config->disableDeleteRelationships;
    }

    /**
     * Get any scalar values and foreign keys from the entity that need saving and return them in a flat array
     * @param object $entity
     * @param array $pkValues
     * @param bool $processChildren
     * @return array Values to be updated, keyed on property name
     * @throws ObjectiphyException|\ReflectionException
     */
    public function unbindEntityToRow(object $entity, array $pkValues = [], bool $processChildren = false): array
    {
        $row = [];
        $properties = $this->entityTracker->getDirtyProperties($entity, $pkValues);
        foreach ($properties as $property => $value) {
            $this->objectMapper->addMappingForProperty(ObjectHelper::getObjectClassName($entity), $property, true);
            $propertyMapping = $this->mappingCollection->getPropertyMapping($property);
            if ($propertyMapping && ($processChildren || !$propertyMapping->getChildClassName() || $propertyMapping->relationship->isEmbedded)
                && $this->mappingCollection->isPropertyFetchable($propertyMapping)) {
                if ($this->disableDeleteRelationships && $propertyMapping->getChildClassName() && !$value) {
                    continue; //Not allowed to remove the relationship
                }
                if ($propertyMapping->relationship->isEmbedded) {
                    //Unbind all values on the embedded object...
                    foreach ($this->mappingCollection->getPropertyMappings([$property]) as $childPropertyMapping) {
                        $columnName = $childPropertyMapping->getFullColumnName();
                        if ($columnName) {
                            $childValue = ObjectHelper::getValueFromObject($value, $childPropertyMapping->propertyName);
                            $row[$childPropertyMapping->getPropertyPath()] = $this->unbindValue($childValue);
                        }
                    }
                } else {
                    $columnName = $propertyMapping->getFullColumnName();
                    if ($columnName) {
                        $pkProperty = '';
                        $unboundValue = $this->unbindValue($value, $propertyMapping->relationship->targetJoinColumn, $pkProperty);
                        if ($pkProperty) {
                            //Check whether this has actually changed - entity tracker will not have known the property to compare before
                            if (!$this->entityTracker->isRelationshipDirty($entity, $property . '.' . $pkProperty)) {
                                continue;
                            }
                        }
                        $row[$property] = $unboundValue;
                    }
                }
            }
        }
        
        return $row;
    }

    /**
     * If value is an entity, extract the primary key value, or if it is an entity without a primary key, and a known
     * column is the foreign key, get the value of the property mapped to that column, otherwise just return the value
     * @param mixed $value
     * @param string $targetJoinColumn
     * @param string|null $pkProperty
     * @return mixed
     */
    public function unbindValue($value, string $targetJoinColumn = '', ?string &$pkProperty = null)
    {
        $result = null;
        if (is_object($value) && !($value instanceof \DateTimeInterface)) {
            $valueClass = ObjectHelper::getObjectClassName($value);
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties($valueClass);
            if ($pkProperties && count($pkProperties) == 1) {
                $pkProperty = reset($pkProperties);
                $result = ObjectHelper::getValueFromObject($value, $pkProperty);
            } elseif ($targetJoinColumn) {
                //Try to find a property that maps to the given column
                $properties = $this->mappingCollection->getPropertyExamplesForClass(ObjectHelper::getObjectClassName($value));
                foreach ($properties ?? [] as $propertyMapping) {
                    if ($propertyMapping->getShortColumnName(false) == $propertyMapping->getShortColumnName(false, $targetJoinColumn)) {
                        $pkProperty = $propertyMapping->propertyName;
                        $result = ObjectHelper::getValueFromObject($value, $pkProperty);
                        break;
                    }
                }
            } else {
                $result = $value; //I hope you know what you're doing, coz I don't.
            }
        } else {
            $result = $value;
        }

        return $result;
    }
}
