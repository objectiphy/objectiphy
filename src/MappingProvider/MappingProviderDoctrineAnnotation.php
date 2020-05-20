<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Doctrine\ORM\Mapping\OrderBy;
use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * A decorator for the annotation mapping provider, which populates any missing information using Doctrine annotations.
 */
class MappingProviderDoctrineAnnotation implements MappingProviderInterface
{
    private MappingProviderInterface $delegate;
    private AnnotationReaderInterface $annotationReader;

    public function __construct(MappingProviderInterface $delegate, AnnotationReaderInterface $annotationReader)
    {
        $this->delegate = $delegate;
        $this->annotationReader = $annotationReader;
    }

    public function getTableMapping(\ReflectionClass $reflectionClass)
    {
        $table = $this->delegate->getTableMapping($reflectionClass);
        if (!isset($table->name) && class_exists('\Doctrine\ORM\Mapping\Table')) {
            $doctrineTable = $this->annotationReader->getClassAnnotation(
                $reflectionClass,
                \Doctrine\ORM\Mapping\Table::class
            );
            if ($doctrineTable) {
                $table = $table ?? new Table();
                $table->name = $doctrineTable->name;
            }
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

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): ?Relationship
    {
        $relationship = $this->delegate->getRelationshipMapping($reflectionProperty);
        foreach (Relationship::getRelationshipTypes() as $relationshipType) {
            $this->populateFromDoctrineRelationship($reflectionProperty, $relationship, $relationshipType);
        }
        $this->populateFromDoctrineJoinColumn($reflectionProperty, $relationship);
        $this->populateFromDoctrineOrderBy($reflectionProperty, $relationship);
        $this->populateFromDoctrineEmbedded($reflectionProperty, $relationship);

        return $relationship;

    }

    private function populateFromDoctrineColumn(\ReflectionProperty $reflectionProperty, Column &$column): void
    {
        if (class_exists('\Doctrine\ORM\Mapping\Column')) {
            if (!isset($column->name) || !isset($column->type)) {
                $doctrineColumn = $this->annotationReader->getPropertyAnnotation(
                    $reflectionProperty,
                    \Doctrine\ORM\Mapping\Column::class
                );
                if ($doctrineColumn) {
                    $column = $column ?? new Column();
                    $column->name = isset($column->name) ? $column->name : $doctrineColumn->name;
                    $column->type = isset($column->type) ? $column->type : $doctrineColumn->type;
                }
            }
        }
    }

    private function populateFromDoctrineId(\ReflectionProperty $reflectionProperty, Column &$column): void
    {
        if (class_exists('\Doctrine\ORM\Mapping\Id')) {
            if (($column->isPrimaryKey ?? null) === null) {
                $doctrineId = $this->annotationReader->getPropertyAnnotation(
                    $reflectionProperty,
                    \Doctrine\ORM\Mapping\Id::class
                );
                if ($doctrineId) {
                    $column = $column ?? new Column();
                    $column->isPrimaryKey = true;
                }
            }
        }
    }

    private function populateFromDoctrineOrderBy(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\OrderBy')) {
            if (!isset($relationship->orderBy)) {
                $doctrineOrderBy = $this->annotationReader->getPropertyAnnotation(
                    $reflectionProperty,
                    \Doctrine\ORM\Mapping\OrderBy::class
                );
                if ($doctrineOrderBy) {
                    $relationship = $relationship ?? new Relationship();
                    $relationship->orderBy = $doctrineOrderBy->value;
                }
            }
        }
    }

    private function populateFromDoctrineRelationship(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        string $relationshipType
    ): void {
        $doctrineClass = '\Doctrine\ORM\Mapping\\' .  str_replace('_', '', ucwords($relationshipType, '_'));
        if (class_exists($doctrineClass) && !isset($relationship->relationshipType)) {
            $doctrineRelationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, $doctrineClass);
            if ($doctrineRelationship) {
                $relationship = $relationship ?? new Relationship();
                $relationship->relationshipType = $relationshipType;
            }
        }
    }

    private function populateFromDoctrineJoinColumn(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {
        
    }

    private function populateFromDoctrineEmbedded(\ReflectionProperty $reflectionProperty, Relationship &$relationship)
    {

    }
}
