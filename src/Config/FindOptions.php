<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class FindOptions extends AbstractOptions implements PropertyPathConsumerInterface
{
    public ?PaginationInterface $pagination = null;
    public bool $multiple = true;
    public bool $latest = false;
    public bool $count = false;
    public bool $bindToEntities = true;
    public bool $onDemand = false;
    public string $indexBy = '';
    public string $scalarProperty = '';
    public bool $allowDuplicates = false;
    
    /**
     * @var array As per Doctrine, but with properties of children also allowed, eg.
     * ['contact.lastName'=>'ASC', 'policyNo'=>'DESC']. Stored here just for reference - it
     * gets added to the query.
     */
    public array $orderBy = [];
    
    /**
     * Create and initialise find options.
     * @param MappingCollection $mappingCollection
     * @param array $settings
     * @return FindOptions
     */
    public static function create(MappingCollection $mappingCollection, array $settings = []): FindOptions
    {
        $findOptions = new FindOptions($mappingCollection);
        parent::initialise($findOptions, $settings);
        
        return $findOptions;
    }

    public function getPropertyPaths(): array
    {
        $paths = [];
        if ($this->indexBy) {
            $paths[] = $this->indexBy;
        }
        if ($this->scalarProperty) {
            $paths[] = $this->scalarProperty;
        }
        
        return $paths;
    }
}
