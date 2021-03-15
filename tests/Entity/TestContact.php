<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Objectiphy\Objectiphy\Mapping\Relationship;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.contact")
 * @property int $id
 * @property string $firstName
 * @property string $lastName
 */
class TestContact
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @Groups({"Default"})
     * @ORM\Column(type="string", name="first_name")
     */
    protected $firstName;
    /**
     * @Groups({"Default"})
     * @ORM\Column(type="string", name="last_name")
     */
    protected $lastName;
    /**
     * @ORM\Column(type="string", name="postcode")
     */
    protected $postcode;
    /**
     * @ORM\Column(type="string", name="title_code")
     */
    protected $title;
    /**
     * This is here to make sure that a column `title` will map to property 'titleText' even though there is also a
     * property named 'title'.
     * @ORM\Column(type="string", name="title")
     */
    protected $titleText;
    /**
     * Unidirectional relationship (TODO: allow for other side of unidirectional relationship to have a scalar ID that stays scalar)
     * @Relationship(relationshipType="one_to_one", childClassName="TestSecurityPass", sourceJoinColumn="security_pass_id")
     */
    protected $securityPass;
    /**
     * Unidirectional relationship on non PK column
     * @Relationship(relationshipType="one_to_one", childClassName="TestNonPkChild", sourceJoinColumn="child_nebulous_identifier", targetJoinColumn="nebulous_identifier")
     */
    protected $nonPkChild;

    /**
     * @var TestDepartment
     * @Relationship(relationshipType="one_to_one", childClassName="TestDepartment", sourceJoinColumn="department_id")
     */
    protected TestDepartment $department;

    /**
     * @var bool
     * @ORM\Column(type="bool")
     */
    protected bool $isPermanent;

    /**
     * @var bool
     * @ORM\Column(type="bool")
     */
    protected bool $earnsCommission;

    /**
     * @var float
     * @ORM\Column(type="decimal")
     */
    protected float $commissionRate;

    /**
     * @var bool
     * @ORM\Column(type="bool")
     */
    protected bool $higherRateEarner;

    /**
     * @var int
     * @ORM\Column(type="int")
     */
    protected int $loginId;

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

    public function getName()
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
