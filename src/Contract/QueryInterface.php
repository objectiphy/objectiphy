<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

interface QueryInterface extends PropertyPathConsumerInterface
{
    public function finalise(MappingCollection $mappingCollection, ?string $className = null);
    public function __toString(): string;
}
