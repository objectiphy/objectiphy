<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * If you want Objectiphy to use a factory to create your entities, use this interface. If the entity created by 
 * your factory is not serializable though, it will not be possible to create a proxy of it - so if a proxy is 
 * needed, Objectiphy will just go ahead and create one itself, without using your factory.
 */
interface EntityFactoryInterface
{
    public function createEntity(string $className): ?object;
}
