<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @Objectiphy\Table(name="objectiphy_test.anomalies")
 * @property int $primary_key
 * @property string $firstName
 * @property string $last_name
 * @property \DateTime $some_random_event_dateTime
 * @property string $a_VERY_Very_InconsistentnamingConvention_here
 * @property TestAddress $address_with_underscores
 */
class TestWeirdPropertyNames
{
    /**
     * @var int
     * @Objectiphy\Column(isPrimaryKey=true,name="id")
     */
    protected $primary_key;
    /**
     * @var string
     * @Objectiphy\Column(type="string", name="first_name")
     */
    protected $firstName;
    /**
     * @var string
     * @Objectiphy\Column(type="string", name="lastname")
     */
    protected $last_name;
    /**
     * @var \DateTime
     * @Objectiphy\Column(type="datetime", name="event_date_time")
     */
    protected $some_random_event_dateTime;
    /**
     * @var string
     * @Objectiphy\Column(type="string", name="veryInconsistent_naming_Conventionhere")
     */
    protected $a_VERY_Very_InconsistentnamingConvention_here;
    /**
     * @Objectiphy\Column(type="TestAddress", relationshipType="one_to_one", embedded=true)
     */
    protected $address_with_underscores;
    /**
     * @var TestUser
     * @Objectiphy\Column(type="TestUser", name="user_id", relationshipType="one_to_one")
     */
    protected $test_user;

    /**
     * This is a dirty bodge, just for unit test purposes - not recommended
     * for real entities in an application
     * @param $property
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
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
