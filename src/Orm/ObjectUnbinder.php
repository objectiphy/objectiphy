<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\DataTypeHandlerInterface;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

final class ObjectUnbinder
{
    private MappingCollection $mappingCollection;
    private EntityTracker $entityTracker;
    private DataTypeHandlerInterface $dataTypeHandler;
    private ObjectMapper $objectMapper;

    public function __construct(
        EntityTracker $entityTracker,
        DataTypeHandlerInterface $dataTypeHandler,
        ObjectMapper $objectMapper
    ) {
        $this->entityTracker = $entityTracker;
        $this->dataTypeHandler = $dataTypeHandler;
        $this->objectMapper = $objectMapper;
    }
    
    public function setMappingCollection(MappingCollection $mappingCollection): void
    {
        $this->mappingCollection = $mappingCollection;
    }

    public function unbindEntityToRows(object $entity, array $pkValues = [], bool $processChildren = false): array
    {
        $rows = [];
        $properties = $this->entityTracker->getDirtyProperties($entity, $pkValues);
        foreach ($properties as $property => $value) {
            $this->objectMapper->addMappingForProperty(ObjectHelper::getObjectClassName($entity), $property, true);
            $propertyMapping = $this->mappingCollection->getPropertyMapping($property);
            if ($propertyMapping && ($processChildren || !$propertyMapping->getChildClassName())
                && $this->mappingCollection->isPropertyFetchable($propertyMapping)) {
                $columnName = $propertyMapping->getFullColumnName();
                if ($columnName) {
                    $rows[$property] = $this->unbindValue($value);
                }
            }
        }
        
        return $rows;
    }

    /**
     * If value is an entity, extract the primary key value, otherwise just return the value
     * @param $value
     * @return \DateTimeInterface|mixed|null
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
