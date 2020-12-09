<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface as NSI;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Table;

class NameResolver
{
    private NamingStrategyInterface $tableNamingStrategy;
    private NamingStrategyInterface $columnNamingStrategy;
    private bool $guessMappings;

    public function setConfigOptions(
        bool $guessMappings = true,
        NamingStrategyInterface $tableNamingStrategy, 
        NamingStrategyInterface $columnNamingStrategy
    ) {
        $this->tableNamingStrategy = $tableNamingStrategy;
        $this->columnNamingStrategy = $columnNamingStrategy;
        $this->guessMappings = $guessMappings;
    }

    /**
     * If we don't know the table name, use naming strategy to convert class name
     * @param \ReflectionClass $reflectionClass
     * @param Table $table
     */
    public function resolveTableName(\ReflectionClass $reflectionClass, Table $table): void
    {
        if ($this->guessMappings && empty($table->name)) {
            $table->name = $this->tableNamingStrategy->convertName(
                $reflectionClass->getShortName(),
                NSI::TYPE_CLASS
            );
        }
    }

    /**
     * If we have a column mapping but without a name, use naming strategy to convert property name, or if we have a
     * relationship mapping but without a source column name (and without deferral of mapping to the other side of the
     * relationship), use naming strategy to convert property name - but all that only if config says we should guess.
     * @param PropertyMapping $propertyMapping
     */
    public function resolveColumnName(PropertyMapping $propertyMapping): void
    {
        //Local variables make the code that follows more readable
        $propertyName = $propertyMapping->propertyName;
        $parentClassName = $propertyMapping->className;
        $relationship = $propertyMapping->relationship;
        $column = $propertyMapping->column;
        $strategy = $this->columnNamingStrategy ?? null;

        if ($this->guessMappings && $strategy) {
            if (empty($column->name) && !$relationship->isDefined()) {
                //Resolve column name for scalar value property
                $column->name = $strategy->convertName(
                    $propertyName,
                    NSI::TYPE_SCALAR_PROPERTY,
                    $propertyMapping
                );
            } elseif ($relationship->isDefined() && (!$relationship->sourceJoinColumn && !$relationship->mappedBy)) {
                if ($relationship->isEmbedded) {
                    return; //Temporary measure until we support embedables.
                }
                //Resolve source join column name (foreign key) for relationship property
                $relationship->sourceJoinColumn = $strategy->convertName(
                    $propertyName,
                    NSI::TYPE_RELATIONSHIP_PROPERTY,
                    $propertyMapping
                );
            }
        }

        if ($relationship->isDefined() && !$column->name) {
            $column->name = $relationship->sourceJoinColumn; //Also retrieve the value in case of late binding
        }
    }
}
