<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

class SaveOptions
{
    public MappingCollection $mappingCollection;
    public bool $saveChildren = true;
    public bool $replaceExisting = false;
    
    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

    public static function create(MappingCollection $mappingCollection, array $settings = [])
    {
        $saveOptions = new SaveOptions($mappingCollection);
        foreach ($settings as $key => $value) {
            if (method_exists($saveOptions, 'set' . ucfirst($key))) {
                $saveOptions->{'set' . ucfirst($key)}($value);
            } elseif (property_exists($saveOptions, $key)) {
                $saveOptions->$key = $value;
            }
        }
        
        return $saveOptions;
    }
    
    public function getClassName(): string
    {
        return $this->mappingCollection->getEntityClassName();
    }
}
