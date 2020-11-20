<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

interface PropertyPathConsumerInterface
{
    public function getPropertyPaths(): array;
}
