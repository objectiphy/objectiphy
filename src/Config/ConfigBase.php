<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Exception\ObjectiphyException;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class ConfigBase
{
    /**
     * @var array Keep track of which options have changed from the default value so that we can cache mappings for a
     * particular configuration.
     */
    private array $nonDefaults = [];

    /**
     * For convenience, you can get config options as properties instead of using the generic getter
     * @param $optionName
     * @return mixed
     * @throws ObjectiphyException
     */
    public function __get(string $optionName)
    {
        return $this->getConfigOption($optionName);
    }

    /**
     * For convenience, you can set config options as properties instead of using the generic setter
     * @param $optionName
     * @param $value
     * @throws ObjectiphyException
     */
    public function __set(string $optionName, $value)
    {
        $this->setConfigOption($optionName, $value);
    }

    /**
     * Set a config option.
     * @param string $optionName
     * @param $value
     * @throws ObjectiphyException
     */
    public function setConfigOption(string $optionName, $value)
    {
        if (property_exists($this, $optionName)) {
            $this->{$optionName} = $value;
            $this->nonDefaults[$optionName] = $value;
        } else {
            $this->throwNotExists($optionName);
        }
    }

    /**
     * Get a config option, if it exists.
     * @param string $optionName
     * @return mixed
     * @throws ObjectiphyException
     */
    public function getConfigOption(string $optionName)
    {
        if (property_exists($this, $optionName)) {
            return $this->{$optionName};
        } else {
            $this->throwNotExists($optionName);
        }
    }

    /**
     * Safely set an individual element of a config option that is an array.
     * @param string $optionName
     * @param string $key
     * @param $value
     * @throws ObjectiphyException
     */
    public function setConfigArrayOption(string $optionName, string $key, $value)
    {
        $this->validateArray($optionName);
        $this->{$optionName}[$key] = $value;
        $this->nonDefaults[$optionName] = $this->{$optionName};
    }

    /**
     * Safely get an individual element of a config option that is an array.
     * @param string $optionName
     * @param string $key
     * @return |null
     * @throws ObjectiphyException
     */
    public function getConfigArrayOption(string $optionName, string $key)
    {
        $this->validateArray($optionName);

        return $this->{$optionName}[$key] ?? null;
    }

    /**
     * Returns a hash uniquely representing the config options that are currently set (to be used as a cache key for
     * mapping information that uses this particular set of config options).
     */
    public function getHash()
    {
        ksort($this->nonDefaults);
        return sha1(serialize($this->nonDefaults));
    }

    /**
     * @param string $optionName
     * @throws ObjectiphyException
     */
    private function validateArray(string $optionName)
    {
        if (!property_exists($this, $optionName)) {
            $this->throwNotExists($optionName);
        } elseif (!is_array($this->{$optionName})) {
            throw new ObjectiphyException(sprintf('Config option %1$s is not an array.', $optionName));
        }
    }

    /**
     * @param string $optionName
     * @throws ObjectiphyException
     */
    private function throwNotExists(string $optionName)
    {
        throw new ObjectiphyException(sprintf('Config option %1$s does not exist.', $optionName));
    }
}