<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Contract\MappingProviderInterface;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * This is just a base component that other providers can decorate depending on how they get their mapping information.
 */
class MappingProvider implements MappingProviderInterface
{
    protected bool $throwExceptions = false;
    protected string $lastErrorMessage = '';
    
    public function setThrowExceptions(bool $value): void
    {
        $this->throwExceptions = $value;
    }
    
    public function getLastErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }

    /**
     * Just return a new Table mapping object without populating it.
     * @param \ReflectionClass $reflectionClass
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Table
     */
    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped = null): Table
    {
        $wasMapped = false;
        return new Table();
    }

    /**
     * Just return a new Column mapping object without populating it.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Column
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Column
    {
        $wasMapped = false;
        return new Column();
    }

    /**
     * Just return a new Relationship mapping object without populating it.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Relationship
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Relationship
    {
        $wasMapped = false;
        return new Relationship(Relationship::UNDEFINED);
    }
}
