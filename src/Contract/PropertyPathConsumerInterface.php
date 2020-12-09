<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * For anything that uses properties, they should implement this interface.
 * @package Objectiphy\Objectiphy\Contract
 */
interface PropertyPathConsumerInterface
{
    public function getPropertyPaths(): array;
}
