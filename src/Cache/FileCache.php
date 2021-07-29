<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Cache;

use Objectiphy\Objectiphy\Exception\CacheException;
use Objectiphy\Annotations\PsrSimpleCacheInvalidArgumentException;
//Conditional import - we won't force you to have Psr\SimpleCache installed
use Objectiphy\Annotations\PsrSimpleCacheInterface;
use Objectiphy\Objectiphy\Exception\CacheInvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

if (!interface_exists('\Psr\SimpleCache\CacheInterface')) {
    class_alias(PsrSimpleCacheInterface::class, '\Psr\SimpleCache\CacheInterface');
}

class FileCache implements \Psr\SimpleCache\CacheInterface
{
    private string $cacheDirectory;
    private string $fileNamePrefix;

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
    }

    public function get($key, $default = null)
    {
        $fileName = $this->getFileName($key);
        try {
            $value = file_exists($fileName) ? unserialize(file_get_contents($fileName)) : $default;
        } catch (\Throwable $ex) {
            //Ignore and just treat it as a cache miss
        }

        return $value ?? null;
    }

    public function set($key, $value, $ttl = null)
    {
        $fileName = $this->getFileName($key);
        try {
            //$ttl not supported, but according to PSR-16 it must be silently ignored or if <= 0, treat as a deletion
            if (is_int($ttl) && $ttl <= 0) {
                return $this->delete($key);
            } else {
                file_put_contents($fileName, serialize($value));
            }
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    public function delete($key)
    {
        $fileName = $this->getFileName($key);
        try {
            if (file_exists($fileName)) {
                return unlink($fileName);
            }
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    public function clear()
    {
        try {
            // Delete all files starting with $this->cacheDirectory . '/obj_cache_' . $this->fileNamePrefix;
            $pattern = $this->getFileName('*');
            $cacheFiles = glob($pattern);
            array_walk($cacheFiles, fn($file) => unlink($file));
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !($keys instanceof \Traversable)) {
            throw new CacheInvalidArgumentException('Must supply an array (or \Traversable instance) of keys when calling getMultiple.');
        }
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values) && !($values instanceof \Traversable)) {
            throw new CacheInvalidArgumentException('Must supply an array (or \Traversable instance) of values when calling setMultiple.');
        }
        $success = true;
        foreach ($values as $key => $value) {
            $itemSuccess = $this->set($key, $value, $ttl);
            $success = !$success ? false : $itemSuccess; //If any item fails, report failure
        }

        return $success;
    }

    public function deleteMultiple($keys)
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

    public function has($key)
    {
        $fileName = $this->getFileName($key);
        return file_exists($fileName);
    }

    private function getFileName(string $key): string
    {
        if (preg_match('/[{}()\/\\\@:]/', $key)) {
            //This is a requirement of PSR-16
            throw new CacheInvalidArgumentException('Cache keys cannot contain any of the following characters: {}()/\@:');
        }
        $fileSuffix = $key === '*' ? $key : str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($key));

        return $this->cacheDirectory . '/obj_cache_' . $this->fileNamePrefix . $fileSuffix . '.txt';
    }
}
