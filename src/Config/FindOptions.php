<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Contract\PropertyPathConsumerInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\CriteriaExpression;
use Objectiphy\Objectiphy\Query\FieldExpression;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Query\SelectQuery;

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
    public ?SelectQuery $query = null;

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

    /**
     * Take in criteria, normalize, and convert to a query
     * @param $criteria
     * @throws QueryException
     */
    public function setCriteria($criteria): void
    {
        if ($criteria instanceof SelectQuery) {
            $this->query = $criteria;
        } elseif (is_array($criteria)) {
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties();
            $normalizedCriteria = QB::create()->normalize($criteria, $pkProperties[0] ?? 'id');
            $this->query = new SelectQuery();
            $this->query->setWhere(...$normalizedCriteria);
        } else {
            throw new QueryException('Invalid criteria specified');
        }
        $this->setOrderBy($this->orderBy);
    }

    /**
     * Apply default order by to query if not already specified on the query
     * @param array $orderBy
     */
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
            $field = new FieldExpression('`' . $property . '` ' . $direction, false);
            $this->orderBy[$property] = $direction;
            $sanitisedOrderBy[] = $field;
        }

        if ($sanitisedOrderBy) {
            if (!$this->query) {
                $this->query = new Query();
            }
            if (!$this->query->getOrderBy()) {
                $this->query->setOrderBy(...$sanitisedOrderBy);
            }
        }
    }

    public function getOrderBy(): ?array
    {
        return $this->orderBy;
    }

    public function getPropertyPaths(): array
    {
        $paths[] = $this->keyProperty;
        if ($this->query) {
            $paths = array_merge($paths, $this->query->getPropertyPaths());
        }
        
        return array_filter(array_unique($paths));
    }
}
