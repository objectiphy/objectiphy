<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OrderBy;
use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * A mapping provider decorator, which populates mapping information using Doctrine annotations.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
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

    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped): Table
    {
        $table = $this->mappingProvider->getTableMapping($reflectionClass, $wasMapped);
        if (class_exists('\Doctrine\ORM\Mapping\Table')) {
            $doctrineTable = $this->annotationReader->getClassAnnotation(
                $reflectionClass,
                \Doctrine\ORM\Mapping\Table::class
            );
            $wasMapped = $wasMapped || $doctrineTable;
            $table->name = $doctrineTable->name ?? $table->name;
        }

        return $table;
    }

    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped): Column
    {
        $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $wasMapped);
        $this->populateFromDoctrineColumn($reflectionProperty, $column, $wasMapped);
        $this->populateFromDoctrineId($reflectionProperty, $column, $wasMapped);

        return $column;
    }

    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped): Relationship
    {
        $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $wasMapped);
        foreach (Relationship::getRelationshipTypes() as $relationshipType) {
            $this->populateFromDoctrineRelationship($reflectionProperty, $relationship, $relationshipType, $wasMapped);
        }
        $this->populateFromDoctrineJoinColumn($reflectionProperty, $relationship, $wasMapped);
        $this->populateFromDoctrineOrderBy($reflectionProperty, $relationship, $wasMapped);
        $this->populateFromDoctrineEmbedded($reflectionProperty, $relationship, $wasMapped);

        return $relationship;

    }

    private function populateFromDoctrineColumn(
        \ReflectionProperty $reflectionProperty, 
        Column &$column, 
        bool &$wasMapped
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\Column')) {
            $doctrineColumn = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\Column::class
            );
            $wasMapped = $wasMapped || $doctrineColumn;
            $column->name = $doctrineColumn->name ?? $column->name;
            $column->type = $doctrineColumn->type ?? $column->type;
        }
    }

    private function populateFromDoctrineId(
        \ReflectionProperty $reflectionProperty, 
        Column &$column,
        bool &$wasMapped
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\Id')) {
            $doctrineId = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\Id::class
            );
            $wasMapped = $wasMapped || $doctrineColumn;
            $column->isPrimaryKey = $doctrineId ? true : $column->isPrimaryKey;
        }
    }

    private function populateFromDoctrineOrderBy(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\OrderBy')) {
            $doctrineOrderBy = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\OrderBy::class
            );
            $wasMapped = $wasMapped || $doctrineOrderBy;
            $relationship->orderBy = $doctrineOrderBy->value ?? $relationship->orderBy;
        }
    }

    private function populateFromDoctrineRelationship(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        string $relationshipType,
        bool &$wasMapped
    ): void {
        $doctrineClass = '\Doctrine\ORM\Mapping\\' .  str_replace('_', '', ucwords($relationshipType, '_'));
        if (class_exists($doctrineClass)) {
            $doctrineRelationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, $doctrineClass);
            $wasMapped = $wasMapped || $doctrineRelationship;
            $relationship->relationshipType = $doctrineRelationship ? $relationshipType : $relationship->relationshipType;
        }
    }

    private function populateFromDoctrineJoinColumn(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\JoinColumn')) {
            $doctrineJoinColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, JoinColumn::class);
            $wasMapped = $wasMapped || $doctrineJoinColumn;
            $relationship->sourceJoinColumn = $doctrineJoinColumn->name ?? $relationship->sourceJoinColumn;
            $relationship->targetJoinColumn = $doctrineJoinColumn->referencedColumnName ?? $relationship->targetJoinColumn;
        }
    }

    private function populateFromDoctrineEmbedded(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('\Doctrine\ORM\Mapping\Embedded')) {
            $doctrineEmbedded = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Embedded::class);
            $wasMapped = $wasMapped || $doctrineEmbedded;
            $relationship->isEmbedded = $doctrineEmbedded ?? $relationship->isEmbedded;
            $relationship->embeddedColumnPrefix = $doctrineEmbedded->columnPrefix ?? $relationship->embeddedColumnPrefix;
            $relationship->childClassName = $doctrineEmbedded->class ?? $relationship->childClassName;
        }
    }
}
