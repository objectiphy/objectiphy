<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Mapping information can come from anywhere - perhaps even a proprietary text file format. As long as you write a 
 * provider that implements this interface, it can be used by Objectiphy. Objectiphy comes with mapping providers for 
 * Doctrine annotations and Objectiphy annotations. A mapping provider can decorate another provider (to fall back to 
 * another mechanism for mapping information, perhaps partially), or can be used on its own.
 */
interface MappingProviderInterface
{
    /**
     * @param bool $value Whether or not to throw exceptions if there is an error parsing mapping information.
     */
    public function setThrowExceptions(bool $value): void;

    /**
     * @return string If not throwing exceptions, store any errors and return them from this method.
     */
    public function getLastErrorMessage(): string;
    
    /**
     * Get table mapping information for a class.
     * @param \ReflectionClass $reflectionClass
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Table
     */
    public function getTableMapping(\ReflectionClass $reflectionClass, bool &$wasMapped = null): Table;

    /**
     * Get column mapping information for a property.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Column
     */
    public function getColumnMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Column;

    /**
     * Get relationship mapping information for a property that represents a child object.
     * @param \ReflectionProperty $reflectionProperty
     * @param bool $wasMapped Output parameter to indicate whether or not some mapping information was specified.
     * @return Relationship
     */
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty, bool &$wasMapped = null): Relationship;
    
    /**
     * Get any serialization groups that the property belongs to, if applicable.
     */
    public function getSerializationGroups(\ReflectionProperty $reflectionProperty): array;
}
