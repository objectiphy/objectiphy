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
            if ($propertyMapping && ($processChildren || !$propertyMapping->getChildClassName())
                && $this->mappingCollection->isPropertyFetchable($propertyMapping)) {
                if ($this->disableDeleteRelationships && $propertyMapping->getChildClassName() && !$value) {
                    continue; //Not allowed to remove the relationship
                }
                $columnName = $propertyMapping->getFullColumnName();
                if ($columnName) {
                    $row[$property] = $this->unbindValue($value);
                }
            }
        }
        
        return $row;
    }

    /**
     * If value is an entity, extract the primary key value, otherwise just return the value
     * @param mixed $value
     * @return mixed
     */
    public function unbindValue($value)
    {
        $result = null;
        if (is_object($value) && !($value instanceof \DateTimeInterface)) {
            $valueClass = ObjectHelper::getObjectClassName($value);
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties($valueClass);
            if ($pkProperties && count($pkProperties) == 1) {
                $result = ObjectHelper::getValueFromObject($value, reset($pkProperties));
            } else {
                $result = $value; //I hope you know what you're doing, coz I don't.
            }
        } else {
            $result = $value;
        }

        return $result;
    }
}
