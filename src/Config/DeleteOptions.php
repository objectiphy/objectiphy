<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

class DeleteOptions
{
    public MappingCollection $mappingCollection;
    public bool $disableCascade = false;
    
    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

    /**
     * Create and initialise save options.
     * @param MappingCollection $mappingCollection
     * @param array $settings
     * @return SaveOptions
     */
    public static function create(MappingCollection $mappingCollection, array $settings = [])
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
