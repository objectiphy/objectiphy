<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Anything that uses properties should implement this interface so that we can make sure we load the mappings for them.
 */
interface PropertyPathConsumerInterface
{
    public function getPropertyPaths(): array;
}
