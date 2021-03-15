<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Mapping\Column;

/**
 * @Table(name="objectiphy_test.supplied_pk")
 * @property string $keyReference
 * @property string $someValue
 */
class TestSuppliedPk
{
    /**
     * @Column(isPrimaryKey=true, autoIncrement=false)
     */
    private $keyReference;
    
    /**
     * @Column
     */
    private string $someValue;
    
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
