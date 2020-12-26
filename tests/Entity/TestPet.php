<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Objectiphy\Objectiphy\Mapping;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.pets")
 * @property int $id
 * @property TestParent $parent
 * @property string $type
 * @property string $name
 * @property int $weightInGrams
 */
class TestPet
{
    /**
     * @ORM\Id
     * @ORM\Column(type="int")
     */
    protected $id;
    /**
     * @ORM\ManyToOne(targetEntity="TestParent", inversedBy="pets")
     * @ORM\JoinColumn(name="parent_id")
     */
    protected $parent;
    /**
     * @ORM\Column(type="string")
     */
    protected $type;
    /**
     * @ORM\Column(type="string")
     */
    protected $name;
    /**
     * @Mapping\Column(type="integer")
     */
    protected $weightInGrams;

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

    public function __isset($property)
    {
        return isset($this->$property);
    }
}
