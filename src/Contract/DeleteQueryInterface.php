<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * Like an SQL query, but with expressions relating to objects and properties.
 */
interface DeleteQueryInterface extends QueryInterface
{
    public function setDelete(string $className): void;

    public function getDelete(): string;

    public function finalise(MappingCollection $mappingCollection, ?string $className = null);
}
