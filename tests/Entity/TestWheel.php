<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.wheel")
 * @property TestVehicle $vehicle
 * @property boolean $loadBearing
 * @property string $description
 */
class TestWheel
{
    /**
     * @ORM\Id
     */
    protected $id;
    
    /**
     * @ORM\ManyToOne(targetEntity="TestVehicle", inversedBy="wheels")
     * @ORM\JoinColumn(name="vehicle_id")
     */
    protected $vehicle;
    
    /**
     * @ORM\Column(type="boolean")
     */
    protected $loadBearing = true;
    
    /**
     * @ORM\Column(type="string")
     */
    protected $description;

    /**
     * This is a dirty bodge, just for unit test purposes - not recommended
     * for real entities in an application
     * @param $property
     * @return mixed
     */
    public function __get($property)
    {
        if (isset($this->$property)) {
            return $this->$property;
        }

        return null;
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
