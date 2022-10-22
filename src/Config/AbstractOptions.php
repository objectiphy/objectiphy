<?php

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Mapping\MappingCollection;

abstract class AbstractOptions
{
    public MappingCollection $mappingCollection;

    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

    public function getClassName(): string
    {
        return $this->mappingCollection->getEntityClassName();
    }

    protected static function initialise(AbstractOptions $options, array $settings)
    {
        foreach ($settings as $key => $value) {
            if (method_exists($options, 'set' . ucfirst($key))) {
                $options->{'set' . ucfirst($key)}($value);
            } elseif (property_exists($options, $key)) {
                $options->$key = $value;
            }
        }
    }
}
