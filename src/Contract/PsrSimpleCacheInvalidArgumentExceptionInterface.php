<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * In case you want to use the reader without a cache, or with the Objectiphy cache, and don't have 
 * Psr\SimpleCache\InvalidArgumentException available, we won't force you to install Psr\SimpleCache - we'll just use this dummy 
 * version instead (one less dependency to worry about).
 */
interface PsrSimpleCacheInvalidArgumentExceptionInterface extends PsrSimpleCacheExceptionInterface
{
    
}
