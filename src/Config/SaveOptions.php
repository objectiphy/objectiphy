<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class SaveOptions extends AbstractOptions
{
    public bool $saveChildren = true;
    public bool $replaceExisting = false;
    public bool $parseDelimiters = true;

    /**
     * Create and initialise save options.
     * @param MappingCollection $mappingCollection
     * @param array $settings
     * @return SaveOptions
     */
    public static function create(MappingCollection $mappingCollection, array $settings = []): SaveOptions
    {
        $saveOptions = new SaveOptions($mappingCollection);
        parent::initialise($saveOptions, $settings);
        
        return $saveOptions;
    }
}
