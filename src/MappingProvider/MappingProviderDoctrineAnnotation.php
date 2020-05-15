<?php

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * A decorator for the annotation mapping provider, which populates any missing information using Doctrine annotations.
 */
class MappingProviderDoctrineAnnotation implements MappingProviderInterface
{
    private MappingProviderInterface $delegate;

    public function __construct(MappingProviderInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    public function getTableMapping(\ReflectionClass $reflectionClass)
    {
        $table = $this->delegate->getTableMapping($reflectionClass);
        if (empty($table->name) && class_exists('\Doctrine\ORM\Mapping\Table')) {
            $doctrineTable = $this->annotationReader->getClassAnnotation(
                $reflectionClass,
                \Doctrine\ORM\Mapping\Table::class
            );
            $table->name = $doctrineTable->name ?? null;
        }

        return $table;
    }

    public function getColumnMapping(\ReflectionProperty $reflectionProperty)
    {
        $column = $this->delegate->getColumnMapping($reflectionProperty);
        $this->populateFromDoctrineColumn($reflectionProperty, $column);
        $this->populateFromDoctrineId($reflectionProperty, $column);

        return $column;
    }

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty)
    {
        $relationship = $this->delegate->getRelationshipMapping($reflectionProperty);
        $this->populateFromDoctrineOneToOne($reflectionProperty, $relationship);
        $this->populateFromDoctrineManyToOne($reflectionProperty, $relationship);
        $this->populateFromDoctrineOneToMany($reflectionProperty, $relationship);
        $this->populateFromDoctrineManyToMany($reflectionProperty, $relationship);
        $this->populateFromDoctrineJoinColumn($reflectionProperty, $relationship);
        $this->populateFromDoctrineOrderBy($reflectionProperty, $relationship);
        $this->populateFromDoctrineEmbedded($reflectionProperty, $relationship);

        return $relationship;

    }

    private function populateFromDoctrineColumn(\ReflectionProperty $reflectionProperty, Column &$column)
    {
        if (class_exists('\Doctrine\ORM\Mapping\Column')) {
            if (!($column->name ?? false) || !($column->type ?? false)) {
                $doctrineColumn = $this->annotationReader->getPropertyAnnotation(
                    $reflectionProperty,
                    \Doctrine\ORM\Mapping\Column::class
                );
                $column->name = $column->name ?? $doctrineColumn->name ?? null;
                $column->type = $column->type ?? $doctrineColumn->type ?? null;
            }
        }
    }

    private function populateFromDoctrineId(\ReflectionProperty $reflectionProperty, Column &$column)
    {
        if (class_exists('\Doctrine\ORM\Mapping\Id')) {
            if (!($column->isPrimaryKey ?? false)) {
                $doctrineId = $this->annotationReader->getPropertyAnnotation(
                    $reflectionProperty,
                    \Doctrine\ORM\Mapping\Id::class
                );
                $column->isPrimaryKey = $doctrineId ?? false;
            }
        }
    }

    private function populateFromDoctrineOrderBy(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {
        
    }

    private function populateFromDoctrineOneToOne(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }

    private function populateFromDoctrineOneToMany(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }

    private function populateFromDoctrineManyToOne(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }

    private function populateFromDoctrineManyToMany(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }

    private function populateFromDoctrineJoinColumn(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }

    private function populateFromDoctrineEmbedded(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }
}
