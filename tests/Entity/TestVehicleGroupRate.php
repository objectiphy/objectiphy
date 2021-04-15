<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;

/**
 * @Table(name="objectiphy_test.rate_vehicle_group")
 * @property int $id
 * @property int $group50
 * @property float $rate
 * @property bool $isFixed
 * @property string $businessType
 * @property string $rule
 * @property string $product
 * @property int $ratingSchemeId
 */
class TestVehicleGroupRate
{
    /**
     * @Column(isPrimaryKey=true)
     */
    protected $id;
    
    /**
     * @Column(type="int", name="group_50")
     */
    protected $group50;
    
    /**
     * @Column(type="decimal")
     */
    protected $rate;

    /**
     * @Column(type="bool")
     */
    protected $isFixed;

    /**
     * @Column 
     */
    protected $businessType;

    /**
     * @Column 
     */
    protected $rule;

    /**
     * @Column 
     */
    protected $product;

    /**
     * @Column(type="int", name="rating_scheme_id")
     */
    protected $ratingScheme;
    
    /**
     * This is a dirty bodge, just for unit test purposes - not recommended
     * for real entities in an application
     * @param $property
     * @return
     */
    public function __get($property)
    {
        if (isset($this->$property)) {
            return $this->$property;
        }
    }

    /**
     * This is a dirty bodge, just for unit test purposes - not recommended
     * for real entities in an application
     * @param $property
     * @param $value
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }
}
