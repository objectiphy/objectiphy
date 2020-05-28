<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;
use Objectiphy\Objectiphy\Mapping\Table;

/**
 * Mapping information can come from anywhere - perhaps even a proprietary text file format. As long as you write a 
 * provider that implements this interface, it can be used by Objectiphy. Objectiphy comes with mapping providers for 
 * Doctrine annotations and Objectiphy annotations. A mapping provider can decorate another provider (to fall back to 
 * another mechanism for mapping information), or be used on its own.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface MappingProviderInterface
{
    public function getTableMapping(\ReflectionClass $reflectionClass): Table;
    public function getColumnMapping(\ReflectionProperty $reflectionProperty): Column;
    public function getRelationshipMapping(\ReflectionProperty $reflectionProperty): Relationship;
}
