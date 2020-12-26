<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class DeleteOptions
{
    public MappingCollection $mappingCollection;
    public bool $disableCascade = false;
    
    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

    /**
     * Create and initialise delete options.
     * @param MappingCollection $mappingCollection
     * @param array $settings
     * @return DeleteOptions
     */
    public static function create(MappingCollection $mappingCollection, array $settings = []): DeleteOptions
    {
        $deleteOptions = new DeleteOptions($mappingCollection);
        foreach ($settings as $key => $value) {
            if (method_exists($deleteOptions, 'set' . ucfirst($key))) {
                $deleteOptions->{'set' . ucfirst($key)}($value);
            } elseif (property_exists($deleteOptions, $key)) {
                $deleteOptions->$key = $value;
            }
        }
        
        return $deleteOptions;
    }

    public function getClassName(): string
    {
        return $this->mappingCollection->getEntityClassName();
    }
}
