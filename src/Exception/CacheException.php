<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Exception;

use Objectiphy\Annotations\PsrSimpleCacheInvalidArgumentException;

//Conditional import - we won't force you to have Psr\SimpleCache installed
if (!interface_exists('\Psr\SimpleCache\CacheException')) {
    class_alias(PsrSimpleCacheException::class, '\Psr\SimpleCache\CacheException');
}

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Exceptions thrown by the cache.
 */
class CacheException extends ObjectiphyException implements \Psr\SimpleCache\CacheException
{

}
