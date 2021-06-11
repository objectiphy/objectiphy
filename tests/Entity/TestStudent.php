<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Objectiphy\Objectiphy\Mapping\Relationship;

/**
 * @ORM\Table(name="objectiphy_test.student")
 * @property $id
 * @property $firstName
 * @property $lastName
 * @property $iq
 * @property $courses
 */
class TestStudent
{
    /**
     * @ORM\Id
     */
    private $id;

    /**
     * @ORM\Column
     */
    private $firstName;

    /**
     * @ORM\Column
     */
    private $lastName;

    /**
     * @ORM\Column(name="intelligence_quotient")
     */
    private $iq;

    /**
     * (annotations can be disabled by removing the @ symbol for test purposes)
     * ManyToMany(targetEntity="TestCourse", mappedBy="students")
     * @Relationship(relationshipType="many_to_many", childClassName="TestCourse", mappedBy="students")
     */
    private array $courses = [];

    /**
     * This is a dirty bodge, just for unit test purposes - not recommended
     * for real entities in an application
     * @param $property
     * @return
     */
    public function &__get($property)
    {
        $value = null;
        if (method_exists($this, 'get' . ucfirst($property))) {
            $value =& $this->{'get' . ucfirst($property)};
        }
        if (property_exists($this, $property)) {
            $value =& $this->$property;
        }

        return $value;
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
    
    public function &getCourses()
    {
        return $this->courses;
    }
}
