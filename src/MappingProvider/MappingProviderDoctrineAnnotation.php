<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Objectiphy\Annotations\AnnotationReaderInterface;
use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Exception\MappingException;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * A mapping provider decorator, which populates mapping information using Doctrine annotations.
 */
class MappingProviderDoctrineAnnotation implements MappingProviderInterface
{
    use MappingProviderExceptionTrait;

    protected MappingProviderInterface $mappingProvider;
    protected AnnotationReaderInterface $annotationReader;

    private $doctrineRelationship;

    public function __construct(MappingProviderInterface $mappingProvider, AnnotationReaderInterface $annotationReader)
    {
        $this->mappingProvider = $mappingProvider;
        $this->annotationReader = $annotationReader;
    }

    /**
     * Populate a Table mapping class based on Doctrine annotations.
     * @param \ReflectionClass $reflectionClass
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Table
     * @throws MappingException
     * @throws \Throwable
     */
    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped = null): Table
    {
        try {
            $this->annotationReader->setThrowExceptions($this->throwExceptions);
            $table = $this->mappingProvider->getTableMapping($reflectionClass, $wasMapped);
            if (class_exists('Doctrine\ORM\Mapping\Table')) {
                $doctrineTable = $this->annotationReader->getClassAnnotation(
                    $reflectionClass,
                    \Doctrine\ORM\Mapping\Table::class
                );
                $wasMapped = $wasMapped || $doctrineTable;
                $table->name = $doctrineTable->name ?? $table->name;
            }

            return $table;
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Table();
        }
    }

    /**
     * Populate a Column mapping class based on Doctrine annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Column
     * @throws MappingException
     * @throws \Throwable
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Column
    {
        try {
            $this->annotationReader->setThrowExceptions($this->throwExceptions);
            $column = $this->mappingProvider->getColumnMapping($reflectionProperty, $wasMapped);
            $this->populateFromDoctrineColumn($reflectionProperty, $column, $wasMapped);
            $this->populateFromDoctrineId($reflectionProperty, $column, $wasMapped);
            return $column;
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Column();
        }
    }

    /**
     * Populate a Relationship mapping class based on Doctrine annotations.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Relationship
     * @throws MappingException
     * @throws \Throwable
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Relationship
    {
        try {
            $this->annotationReader->setThrowExceptions($this->throwExceptions);
            $relationship = $this->mappingProvider->getRelationshipMapping($reflectionProperty, $wasMapped);
            foreach (Relationship::getRelationshipTypes() as $relationshipType) {
                $this->populateFromDoctrineRelationship(
                    $reflectionProperty,
                    $relationship,
                    $relationshipType,
                    $wasMapped
                );
            }
            $this->populateFromDoctrineJoinColumn($reflectionProperty, $relationship, $wasMapped);
            $this->populateFromDoctrineOrderBy($reflectionProperty, $relationship, $wasMapped);
            $this->populateFromDoctrineEmbedded($reflectionProperty, $relationship, $wasMapped);
            $this->populateFromDoctrineId($reflectionProperty, $relationship, $wasMapped);
            $this->populateFromDoctrineJoinTable($reflectionProperty, $relationship, $wasMapped);

            return $relationship;
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return new Relationship();
        }
    }

    /**
     * Get any serialization groups that the property belongs to, if applicable.
     */
    public function getSerializationGroups(\ReflectionProperty $reflectionProperty): array
    {
        return [];
    }

    /**
     * Read a Doctrine Column annotation.
     * @param \ReflectionProperty $reflectionProperty
     * @param Column $column
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineColumn(
        \ReflectionProperty $reflectionProperty, 
        Column &$column, 
        bool &$wasMapped
    ): void {
        if (class_exists('Doctrine\ORM\Mapping\Column')) {
            $doctrineColumn = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\Column::class
            );
            $wasMapped = $wasMapped || $doctrineColumn;
            $column->name = $doctrineColumn->name ?? $column->name;
            $column->type = $doctrineColumn->type ?? $column->type;
        }
    }

    /**
     * Read a Doctrine Id annotation.
     * @param \ReflectionProperty $reflectionProperty
     * @param object $columnOrRelationship
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineId(
        \ReflectionProperty $reflectionProperty, 
        object &$columnOrRelationship,
        bool &$wasMapped
    ): void {
        if (class_exists('Doctrine\ORM\Mapping\Id')) {
            $doctrineId = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\Id::class
            );
            $wasMapped = $wasMapped || $doctrineId;
            $columnOrRelationship->isPrimaryKey = $doctrineId ? true : $columnOrRelationship->isPrimaryKey;
        }
    }

    /**
     * Read a Doctrine OrderBy annotation.
     * @param \ReflectionProperty $reflectionProperty
     * @param Relationship $relationship
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineOrderBy(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('Doctrine\ORM\Mapping\OrderBy')) {
            $doctrineOrderBy = $this->annotationReader->getPropertyAnnotation(
                $reflectionProperty,
                \Doctrine\ORM\Mapping\OrderBy::class
            );
            $wasMapped = $wasMapped || $doctrineOrderBy;
            $relationship->orderBy = $doctrineOrderBy->value ?? $relationship->orderBy;
        }
    }

    /**
     * Read a Doctrine relationship annotation (eg. OneToOne, OneToMany, etc.)
     * @param \ReflectionProperty $reflectionProperty
     * @param Relationship $relationship
     * @param string $relationshipType
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineRelationship(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        string $relationshipType,
        bool &$wasMapped
    ): void {
        $doctrineClass = 'Doctrine\ORM\Mapping\\' .  str_replace('_', '', ucwords($relationshipType, '_'));
        if (class_exists($doctrineClass)) {
            $doctrineRelationship = $this->annotationReader->getPropertyAnnotation($reflectionProperty, $doctrineClass);
            if ($doctrineRelationship) {
                $wasMapped = true;
                $this->doctrineRelationship = $doctrineRelationship;
            }
            $relationship->relationshipType = $doctrineRelationship ? $relationshipType : $relationship->relationshipType;
            $relationship->mappedBy = $doctrineRelationship->mappedBy ?? $relationship->mappedBy;
            $relationship->lazyLoad = isset($doctrineRelationship->fetch) ? $doctrineRelationship->fetch == 'LAZY' : $relationship->lazyLoad;
            $relationship->orphanRemoval = $doctrineRelationship->orphanRemoval ?? $relationship->orphanRemoval;
            $relationship->childClassName = $doctrineRelationship->targetEntity ?? $relationship->childClassName;
            $relationship->indexBy = $doctrineRelationship->indexBy ?? $relationship->indexBy;
        }
    }

    /**
     * Read a Doctrine JoinTable annotation.
     * @param \ReflectionProperty $reflectionProperty
     * @param Relationship $relationship
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineJoinTable(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('Doctrine\ORM\Mapping\JoinTable')) {
            $doctrineJoinTable = $this->annotationReader->getPropertyAnnotation($reflectionProperty, JoinTable::class);
            $wasMapped = $wasMapped || $doctrineJoinTable;
            if ($relationship->relationshipType == Relationship::MANY_TO_MANY && !$relationship->mappedBy) {
                $relationship->bridgeJoinTable = $doctrineJoinTable->name ?? $relationship->joinTable;
                if (!$relationship->bridgeJoinTable && $this->doctrineRelationship->inversedBy ?? false) {
                    //We need to make a note of $this->doctrineRelationship->inversedBy
                    //so we can use it to build bridgeJoinTable in the name resolver
                    $relationship->bridgeJoinTable = '[calculated]_' . $this->doctrineRelationship->inversedBy;
                }
                $sourceJoinColumns = [];
                $bridgeSourceJoinColumns = [];
                $bridgeTargetJoinColumns = [];
                $targetJoinColumns = [];
                //Use ['calculated'] as a placeholder for any undefined items and we will guess the values later
                foreach ($doctrineJoinTable->joinColumns ?? [] as $joinColumn) {
                    $sourceJoinColumns[] = $joinColumn->referencedColumnName ?: '[calculated]';
                    $bridgeSourceJoinColumns[] = $joinColumn->name ?: '[calculated]';
                }
                foreach ($doctrineJoinTable->inverseJoinColumns ?? [] as $targetJoinColumn) {
                    $bridgeTargetJoinColumns[] = $targetJoinColumn->name ?: '[calculated]';
                    $targetJoinColumns[] = $targetJoinColumn->referencedColumnName ?: '[calculated]';
                }
                $relationship->sourceJoinColumn = implode(',', $sourceJoinColumns ?: ['[calculated]']);
                $relationship->bridgeSourceJoinColumn = implode(',', $bridgeSourceJoinColumns ?: ['[calculated]']);
                $relationship->bridgeTargetJoinColumn = implode(',', $bridgeTargetJoinColumns ?: ['[calculated]']);
                $relationship->targetJoinColumn = implode(',', $targetJoinColumns ?: ['[calculated]']);
            } else {
                $relationship->joinTable = $doctrineJoinTable->name ?? $relationship->joinTable;
            }
        }
    }

    /**
     * Read a Doctrine JoinColumn annotation.
     * @param \ReflectionProperty $reflectionProperty
     * @param Relationship $relationship
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineJoinColumn(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('Doctrine\ORM\Mapping\JoinColumn')) {
            $doctrineJoinColumn = $this->annotationReader->getPropertyAnnotation($reflectionProperty, JoinColumn::class);
            $wasMapped = $wasMapped || $doctrineJoinColumn;
            $relationship->sourceJoinColumn = $doctrineJoinColumn->name ?? $relationship->sourceJoinColumn;
            $relationship->targetJoinColumn = $doctrineJoinColumn->referencedColumnName ?? $relationship->targetJoinColumn;
        }
    }

    /**
     * Read a Doctrine Embedded annotation.
     * @param \ReflectionProperty $reflectionProperty
     * @param Relationship $relationship
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     */
    private function populateFromDoctrineEmbedded(
        \ReflectionProperty $reflectionProperty,
        Relationship &$relationship,
        bool &$wasMapped
    ): void {
        if (class_exists('Doctrine\ORM\Mapping\Embedded')) {
            $doctrineEmbedded = $this->annotationReader->getPropertyAnnotation($reflectionProperty, Embedded::class);
            $wasMapped = $wasMapped || $doctrineEmbedded;
            $relationship->isEmbedded = boolval($doctrineEmbedded ?? $relationship->isEmbedded);
            $relationship->embeddedColumnPrefix = ($doctrineEmbedded->columnPrefix ?? false) ? strval($doctrineEmbedded->columnPrefix) : $relationship->embeddedColumnPrefix;
            $relationship->childClassName = $doctrineEmbedded->class ?? $relationship->childClassName;
            if ($relationship->isEmbedded && $relationship->childClassName) {
                $relationship->relationshipType = Relationship::ONE_TO_ONE;
            }
        }
    }
}
