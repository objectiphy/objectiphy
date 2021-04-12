<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\NamingStrategy;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\Mapping\PropertyMapping;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class NameResolver
{
    private NamingStrategyInterface $tableNamingStrategy;
    private NamingStrategyInterface $columnNamingStrategy;
    private bool $guessMappings = false;

    public function setConfigOptions(ConfigOptions $config): void
    {
        $this->tableNamingStrategy = $config->tableNamingStrategy;
        $this->columnNamingStrategy = $config->columnNamingStrategy;
        $this->guessMappings = $config->guessMappings;
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
                NamingStrategyInterface::TYPE_CLASS
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
        $relationship = $propertyMapping->relationship;
        $column = $propertyMapping->column;
        $strategy = $this->columnNamingStrategy ?? null;
        //If we added a placeholder, remove it now so we can populate the real value.
        $relationship->sourceJoinColumn = substr($relationship->sourceJoinColumn, 0, 1) == '[' ? '' : $relationship->sourceJoinColumn;

        if ($this->guessMappings && $strategy) {
            if (empty($column->name) && !$relationship->isDefined()) {
                //Resolve column name for scalar value property
                $column->name = $strategy->convertName(
                    $propertyName,
                    NamingStrategyInterface::TYPE_SCALAR_PROPERTY,
                    $propertyMapping
                );
            } elseif ($relationship->isDefined() && (!$relationship->sourceJoinColumn && !$relationship->mappedBy)) {
                if ($relationship->isEmbedded) {
                    return; //There is no join
                }
                //Resolve source join column name (foreign key) for relationship property
                $relationship->sourceJoinColumn = $strategy->convertName(
                    $relationship->isToMany() ? $strategy->dePluralise($propertyName) : $propertyName,
                    NamingStrategyInterface::TYPE_RELATIONSHIP_PROPERTY,
                    $propertyMapping
                );
            }
        }

        if ($relationship->isDefined() && !$column->name) {
            $column->name = $relationship->sourceJoinColumn; //Also retrieve the value in case of late binding
        }
    }
}
