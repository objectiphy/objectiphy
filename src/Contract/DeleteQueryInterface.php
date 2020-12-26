<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Like an SQL query, but with expressions relating to objects and properties.
 */
interface DeleteQueryInterface extends QueryInterface
{
    public function setDelete(string $className): void;

    public function getDelete(): string;

    /**
     * Fill in any missing parts using the given mapping collection.
     * @param MappingCollection $mappingCollection
     * @param string|null $className
     * @return mixed
     */
    public function finalise(MappingCollection $mappingCollection, ?string $className = null);
}
