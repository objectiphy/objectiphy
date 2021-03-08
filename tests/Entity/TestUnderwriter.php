<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Objectiphy\Objectiphy\Mapping;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.underwriter")
 * @property int $id
 * @property string $name
 */
class TestUnderwriter
{
    /**
     * @var int
     * @Objectiphy\Objectiphy\Mapping\Groups({"Default"})
     * @Mapping\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @ORM\Column(type="string")
     */
    protected $name;

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
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }
}
