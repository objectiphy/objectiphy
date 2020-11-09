<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\Query;

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
    public ?Query $query = null;

    /**
     * @var array As per Doctrine, but with properties of children also allowed, eg.
     * ['contact.lastName'=>'ASC', 'policyNo'=>'DESC']. Stored here just for reference - it
     * gets added to the query.
     */
    private array $orderBy = [];

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

    public function setCriteria($criteria): void
    {
        if ($criteria instanceof Query) {
            $this->query = $criteria;
        } elseif (is_array($criteria)) {
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties();
            $normalizedCriteria = QB::create()->normalize($criteria, $pkProperties[0] ?? 'id');
            $this->query = new Query();
            $this->query->setWhere(...$normalizedCriteria);
        } else {
            throw new QueryException('Invalid criteria specified');
        }
        $this->setOrderBy($this->orderBy);
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
            $field = new FieldExpression('`' . $property . '` ' . $direction);
            $this->orderBy[$property] = $direction;
            $sanitisedOrderBy[] = $field;
        }

        if (!$this->query) {
            $this->query = new Query();
        }
        $this->query->setOrderBy(...$sanitisedOrderBy);
    }

    public function getOrderBy(): ?array
    {
        return $this->orderBy;
    }
}
