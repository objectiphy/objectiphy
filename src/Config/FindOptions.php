<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\QB;

class FindOptions
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

    /**
     * @var array As per Doctrine, but with properties of children also allowed, eg.
     * ['contact.lastName'=>'ASC', 'policyNo'=>'DESC']
     */
    private ?array $orderBy = null;
    private array $criteria = [];
    
    public function __construct(MappingCollection $mappingCollection)
    {
        $this->mappingCollection = $mappingCollection;
    }

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
    
    public function setCriteria(array $criteria): void
    {
        $this->criteria = QB::create()->normalize($criteria);
    }

    /**
     * @return CriteriaExpression[]
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function setOrderBy(array $orderBy): void
    {
        $sanitisedOrderBy = [];
        foreach ($orderBy as $property => $direction) {
            if (is_int($property) && !in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                $property = $direction; //Indexed array, not associative
                $direction = 'ASC';
            } elseif (!in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                $direction = 'ASC';
            }
            $sanitisedOrderBy[$property] = $direction;
        }

        $this->orderBy = $sanitisedOrderBy;
    }

    public function getOrderBy(): ?array
    {
        return $this->orderBy;
    }
}
