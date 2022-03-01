<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class SaveOptions
{
    public MappingCollection $mappingCollection;
    public bool $saveChildren = true;
    public bool $replaceExisting = false;
    public bool $parseDelimiters = true;
    
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
    public static function create(MappingCollection $mappingCollection, array $settings = []): SaveOptions
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
