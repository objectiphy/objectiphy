<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.telematics_box")
 * @property TestVehicle $vehicle
 * @property string $unitId
 * @property \DateTime $statusDate
 */
class TestTelematicsBox
{
    /**
     * @ORM\Id
     * @ORM\OneToOne(targetEntity="TestVehicle", inversedBy="telematicsBox")
     * @ORM\JoinColumn(name="vehicle_id")
     */
    protected $vehicle;
    /**
     * @ORM\Column(type="string", name="imei")
     */
    protected $unitId;
    /**
     * @ORM\Column(type="datetime")
     */
    protected $statusDate;

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
