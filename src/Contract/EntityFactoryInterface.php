<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * If you want Objectiphy to use a factory to create your entities, use this interface.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface EntityFactoryInterface
{
    public function createEntity(string $entityClassName): object;
}
