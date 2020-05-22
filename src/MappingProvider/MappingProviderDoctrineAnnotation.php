<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OrderBy;
use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * A mapping provider decorator, which populates mapping information using Doctrine annotations.
 */
class MappingProviderDoctrineAnnotation implements MappingProviderInterface
{
    private MappingProviderInterface $mappingProvider;
    private AnnotationReaderInterface $annotationReader;

    public function __construct(MappingProviderInterface $mappingProvider, AnnotationReaderInterface $annotationReader)
    {
        $this->mappingProvider = $mappingProvider;
        $this->annotationReader = $annotationReader;
    }

    public function getTableMapping(\ReflectionClass $reflectionClass): Table
    {
        $table = $this->mappingProvider->getTableMapping($reflectionClass);
        if (class_exists('\Doctrine\ORM\Mapping\Table')) {
            $doctrineTable = $this->annotationReader->getClassAnnotation(
                $reflectionClass,
                \Doctrine\ORM\Mapping\Table::class
            );
            $table->name = $doctrineTable->name ?? $table->name;
        }

        return $table;
    }

    public function getColumnMapping(\ReflectionProperty $reflectionProperty): Column
    {
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty);
        $this->populateFromDoctrineColumn($reflectionProperty, $column);
        $this->populateFromDoctrineId($reflectionProperty, $column);

        return $column;
    }

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): Relationship
    {
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty);
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
            $doctrineColumn = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\Column::class
            );
            $column->name = $doctrineColumn->name ?? $column->name;
            $column->type = $doctrineColumn->type ?? $column->type;
        }
    }

    private function populateFromDoctrineId(\ReflectionProperty $reflectionProperty, Column &$column): void
    {
        if (class_exists('\Doctrine\ORM\Mapping\Id')) {
            $doctrineId = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\Id::class
            );
            $column->isPrimaryKey = $doctrineId ? true : $column->isPrimaryKey;
        }
    }

    private function populateFromDoctrineOrderBy(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\OrderBy')) {
            $doctrineOrderBy = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\OrderBy::class
            );
            $relationship->orderBy = $doctrineOrderBy->value ?? $relationship->orderBy;
        }
    }

    private function populateFromDoctrineRelationship(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        string $relationshipType
    ): void {
        $doctrineClass = '\Doctrine\ORM\Mapping\\' .  str_replace('_', '', ucwords($relationshipType, '_'));
        if (class_exists($doctrineClass)) {
            $doctrineRelationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, $doctrineClass);
            $relationship->relationshipType = $doctrineRelationship ? $relationshipType : $relationship->relationshipType;
        }
    }

    private function populateFromDoctrineJoinColumn(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\JoinColumn')) {
            $doctrineJoinColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, JoinColumn::class);
            $relationship->sourceJoinColumn = $doctrineJoinColumn->name ?? $relationship->sourceJoinColumn;
            $relationship->targetJoinColumn = $doctrineJoinColumn->referencedColumnName ?? $relationship->targetJoinColumn;
        }
    }

    private function populateFromDoctrineEmbedded(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\Embedded')) {
            $doctrineEmbedded = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Embedded::class);
            $relationship->isEmbedded = $doctrineEmbedded ?? $relationship->isEmbedded;
            $relationship->embeddedColumnPrefix = $doctrineEmbedded->columnPrefix ?? $relationship->embeddedColumnPrefix;
            $relationship->childClassName = $doctrineEmbedded->class ?? $relationship->childClassName;
        }
    }
}
