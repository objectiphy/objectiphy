<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

class FindOptions implements PropertyPathConsumerInterface
{
    public MappingCollection $mappingCollection;
    public ?PaginationInterface $pagination = null;
    public bool $multiple = true;
    public bool $latest = false;
    public bool $count = false;
    public bool $bindToEntities = true;
    public bool $onDemand = false;
    public string $keyProperty = '';
    public string $scalarProperty = '';
    public bool $bypassEntityCache = false;
    /**
     * @var array As per Doctrine, but with properties of children also allowed, eg.
     * ['contact.lastName'=>'ASC', 'policyNo'=>'DESC']. Stored here just for reference - it
     * gets added to the query.
     */
    public array $orderBy = [];

    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

    /**
     * Create and initialise find options.
     * @param MappingCollection $mappingCollection
     * @param array $settings
     * @return FindOptions
     */
    public static function create(MappingCollection $mappingCollection, array $settings = [])
    {
        $findOptions = new FindOptions($mappingCollection);
        foreach ($settings as $key => $value) {
            if (method_exists($findOptions, 'set' . ucfirst($key))) {
                $findOptions->{'set' . ucfirst($key)}($value);
            } elseif (property_exists($findOptions, $key)) {
                $findOptions->$key = $value;
            }
        }
        
        return $findOptions;
    }
    
    public function getClassName(): string
    {
        return $this->mappingCollection->getEntityClassName();
    }

    public function getPropertyPaths(): array
    {
        $paths = [];
        if ($this->keyProperty) {
            $paths[] = $this->keyProperty;
        }
        if ($this->scalarProperty) {
            $paths[] = $this->scalarProperty;
        }
        
        return $paths;
    }
}
