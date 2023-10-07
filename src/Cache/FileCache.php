<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Cache;

use Objectiphy\Objectiphy\Exception\CacheException;
use Objectiphy\Annotations\PsrSimpleCacheInterface;
use Objectiphy\Objectiphy\Exception\CacheInvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Traversable;

if (!interface_exists('\Psr\SimpleCache\CacheInterface')) {
    class_alias(PsrSimpleCacheInterface::class, '\Psr\SimpleCache\CacheInterface');
}

class FileCache implements \Psr\SimpleCache\CacheInterface
{
    private string $cacheDirectory;
    private string $fileNamePrefix;
    private bool $useIgBinary = false;

    public function __construct(string $cacheDirectory, string $fileNamePrefix = '')
    {
        if (!$cacheDirectory) {
            throw new CacheException('Cache directory not specified.');
        }
        if (!file_exists($cacheDirectory)) {
            throw new CacheException('Cache directory does not exist. ' . $cacheDirectory);
        }
        if (!is_dir($cacheDirectory)) {
            throw new CacheException('Cache directory specified is not a directory! ' . $cacheDirectory);
        }
        if (!is_writable($cacheDirectory)) {
            throw new CacheException('Cache directory is not writeable. ' . $cacheDirectory);
        }
        $this->cacheDirectory = $cacheDirectory;
        $this->fileNamePrefix = $fileNamePrefix;
        if (function_exists('igbinary_serialize')) {
            $this->useIgBinary = true;
            $this->fileNamePrefix = 'ig' . $this->fileNamePrefix;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fileName = $this->getFileName($key);
        try {
            $value = file_exists($fileName) ? $this->unserialize(file_get_contents($fileName)) : $default;
        } catch (\Throwable $ex) {
            //Ignore and just treat it as a cache miss
        }

        return $value ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $fileName = $this->getFileName($key);
        try {
            //$ttl not supported, but according to PSR-16 it must be silently ignored or if <= 0, treat as a deletion
            if (is_int($ttl) && $ttl <= 0) {
                return $this->delete($key);
            } else {
                file_put_contents($fileName, $this->serialize($value));
            }
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $fileName = $this->getFileName($key);
        try {
            if (is_file($fileName)) {
                return unlink($fileName);
            }
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            // Delete all files starting with $this->cacheDirectory . '/obj_cache_' . $this->fileNamePrefix;
            $pattern = $this->getFileName('*');
            $cacheFiles = glob($pattern);
            array_walk($cacheFiles, fn($file) => is_file($file) && unlink($file));
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    public function getMultiple(\Traversable|array $keys, mixed $default = null): \Traversable|array
    {
        if (!is_array($keys) && !($keys instanceof \Traversable)) {
            throw new CacheInvalidArgumentException(
                'Must supply an array (or \Traversable instance) of keys when calling getMultiple.'
            );
        }
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        if (!is_array($values) && !($values instanceof \Traversable)) {
            throw new CacheInvalidArgumentException(
                'Must supply an array (or \Traversable instance) of values when calling setMultiple.'
            );
        }
        $success = true;
        foreach ($values as $key => $value) {
            $itemSuccess = $this->set($key, $value, $ttl);
            $success = !$success ? false : $itemSuccess; //If any item fails, report failure
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        if (!is_array($keys)) {
            throw new CacheInvalidArgumentException('Must supply an array of keys when calling getMultiple.');
        }
        $success = true;
        foreach ($keys as $key) {
            $itemSuccess = $this->delete($key);
            $success = !$success ? false : $itemSuccess; //If any item fails, report failure
        }

        return $success;
    }

    public function has(string $key): bool
    {
        $fileName = $this->getFileName($key);
        return file_exists($fileName);
    }

    private function getFileName(string $key): string
    {
        if (preg_match('/[{}()\/\\\@:]/', $key)) {
            //This is a requirement of PSR-16
            throw new CacheInvalidArgumentException(
                'Cache keys cannot contain any of the following characters: {}()/\@:'
            );
        }
        $fileSuffix = $key === '*' ? $key : str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($key));

        return $this->cacheDirectory . '/obj_cache_' . $this->fileNamePrefix . $fileSuffix . '.txt';
    }

    private function serialize(mixed $value): string
    {
        return $this->useIgBinary ? igbinary_serialize($value) : serialize($value);
    }

    private function unserialize(string $value): mixed
    {
        return $this->useIgBinary ? igbinary_unserialize($value) : unserialize($value);
    }
}
