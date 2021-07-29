<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Exception;

use Objectiphy\Annotations\PsrSimpleCacheInvalidArgumentException;

//Conditional import - we won't force you to have Psr\SimpleCache installed
if (!interface_exists('\Psr\SimpleCache\InvalidArgumentException')) {
    class_alias(PsrSimpleCacheInvalidArgumentException::class, '\Psr\SimpleCache\InvalidArgumentException');
}

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Exceptions thrown by the cache.
 */
class CacheInvalidArgumentException extends CacheException implements \Psr\SimpleCache\InvalidArgumentException
{

}
