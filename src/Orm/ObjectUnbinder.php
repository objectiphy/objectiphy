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
    
    public function __construct(EntityTracker $entityTracker, DataTypeHandlerInterface $dataTypeHandler)
    {
        $this->entityTracker = $entityTracker;
        $this->dataTypeHandler = $dataTypeHandler;
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
            $propertyMapping = $this->mappingCollection->getPropertyMapping($property);
            if (($processChildren || !$propertyMapping->getChildClassName())
                && $this->mappingCollection->isPropertyFetchable($propertyMapping)) {
                $columnName = $propertyMapping->getFullColumnName();
                if ($columnName) {
                    $rows[$propertyMapping->propertyName] = $value;
//                    $column = $propertyMapping->column;
//                    if ($this->dataTypeHandler->toPersistenceValue($value, $column->type, $column->format)) {
//                        $rows[$columnName] = $value;
//                    }
                }
            }
        }
        
        return $rows;
    }
}
