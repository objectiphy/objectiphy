<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

class FindOptions
{
    public MappingCollection $mappingCollection;
    public string $className;
    public array $criteria;
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
}
