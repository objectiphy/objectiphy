<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\SelectQueryInterface;
use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Query\QB;

class InternalQueryHelper
{
    private ObjectMapper $objectMapper;
    private SqlStringReplacer $stringReplacer;

    public function __construct(ObjectMapper $objectMapper, SqlStringReplacer $stringReplacer)
    {
        $this->objectMapper = $objectMapper;
        $this->stringReplacer = $stringReplacer;
    }

    public function selectOneToManyChildren(
        object $entity,
        PropertyMapping $childPropertyMapping,
        array $childPks,
        string $parentProperty
    ): SelectQueryInterface {
        $query = QB::create()
            ->select(...$childPks)
            ->from($childPropertyMapping->getChildClassName())
            ->where($parentProperty, QB::EQ, $entity)
            ->buildSelectQuery();

        return $query;
    }

    public function selectManyToManyChildren(
        object $entity,
        PropertyMapping $childPropertyMapping,
        array $childPks
    ): SelectQueryInterface {
        $delimit = function($string) { return $this->stringReplacer->delimit($string); }; //Just for ease of use
        $parentClass = ObjectHelper::getObjectClassName($entity);
        $parentMapping = $this->objectMapper->getMappingCollectionForClass($parentClass);
        $childTable = $parentMapping->getTableForClass($childPropertyMapping->getChildClassName())->name;
        $relationship = $childPropertyMapping->relationship;
        $joinAlias = uniqid('obj_many_');
        $joinAlias2 = uniqid('obj_many_');
        $sourceColumns = explode(',', $relationship->sourceJoinColumn);
        $bridgeSourceColumns = explode(',', $relationship->bridgeSourceJoinColumn);
        $bridgeTargetColumns = explode(',', $relationship->bridgeTargetJoinColumn);
        $qb = QB::create()
            ->select(...$childPks)
            ->from($childPropertyMapping->getChildClassName())
            ->innerJoin($delimit($relationship->bridgeJoinTable), $joinAlias)
            ->on($delimit($childTable . '.' . reset($childPks)), '=', $delimit($joinAlias . '.' . reset($bridgeTargetColumns)));
        if (count($childPks) > 1 && count($bridgeTargetColumns) == count($childPks)) {
            for ($index = 1; $index <= count($childPks); $index++) {
                $qb->and($delimit($childTable . '.' . $childPks[$index]), '=', $joinAlias . '.' . $delimit($bridgeTargetColumns[$index]));
            }
        }
        $qb->innerJoin($childPropertyMapping->className, $joinAlias2)
            ->on($delimit($joinAlias . '.' . reset($bridgeSourceColumns)), '=', $delimit($joinAlias2 . '.' . reset($sourceColumns)));
        if (count($bridgeSourceColumns) > 1 && count($sourceColumns) == count($bridgeSourceColumns)) {
            for ($index = 1; $index <= count($bridgeSourceColumns); $index++) {
                $qb->and($delimit($joinAlias . '.' . $bridgeSourceColumns[$index]), '=', $delimit($joinAlias2 . '.' . $sourceColumns[$index]));
            }
        }
        $pkValues = $parentMapping->getPrimaryKeyValues($entity);
        $parentColumn = $parentMapping->getPropertyMapping(array_key_first($pkValues))->getShortColumnName(false);
        $qb->where($delimit($joinAlias2 . '.' . $parentColumn), '=', reset($pkValues));
        if (count($pkValues) > 1) {
            foreach ($pkValues as $key => $value) {
                if ($key != array_key_first($pkValues)) {
                    $parentColumn = $parentMapping->getPropertyMapping($key)->getShortColumnName(false);
                    $qb->and($delimit($joinAlias2 . '.' . $parentColumn), '=', $value);
                }
            }
        }
        $query = $qb->buildSelectQuery();

        return $query;
    }
}
