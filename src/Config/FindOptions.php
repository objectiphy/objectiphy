<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

class FindOptions
{
    public MappingCollection $mappingCollection;
    public array $criteria = [];
    public ?PaginationInterface $pagination = null;
    /**
     * @var array As per Doctrine, but with properties of children also allowed, eg.
     * ['contact.lastName'=>'ASC', 'policyNo'=>'DESC']
     */
    public array $orderBy = [];
    public bool $multiple = true;
    public bool $latest = false;
    public bool $count = false;
    public bool $bindToEntities = true;
    public bool $onDemand = false;
    public string $keyProperty = '';
    public string $scalarProperty = '';
    
    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

    public static function create(MappingCollection $mappingCollection, array $settings = [])
    {
        $findOptions = new FindOptions($mappingCollection);
        foreach ($settings as $key => $value) {
            if (property_exists($findOptions, $key)) {
                $findOptions->$key = $value;
            }
        }
        
        return $findOptions;
    }
    
    public function getClassName()
    {
        return $this->mappingCollection->getEntityClassName();
    }
}
