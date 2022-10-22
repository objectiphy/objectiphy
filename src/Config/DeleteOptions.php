<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class DeleteOptions extends AbstractOptions
{
    public bool $disableCascade = false;

    /**
     * Create and initialise delete options.
     * @param MappingCollection $mappingCollection
     * @param array $settings
     * @return DeleteOptions
     */
    public static function create(MappingCollection $mappingCollection, array $settings = []): DeleteOptions
    {
        $deleteOptions = new DeleteOptions($mappingCollection);
        parent::initialise($deleteOptions, $settings);
        
        return $deleteOptions;
    }
}
