<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.policy")
 * @property int $id
 * @property TestUnderwriter $underwriter
 * @property string $policyNo
 * @property \DateTime $effectiveStartDateTime
 * @property \DateTime $effectiveEndDateTime
 * @property string $status
 * @property string $modification
 * @property TestContact $contact
 * @property TestVehicle $vehicle
 * @property int $loginId
 */
class TestPolicy
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"Default"})
     */
    protected $id;
    /**
     * @var TestUnderwriter
     * @Objectiphy\Column(name="underwriter_id",type="TestUnderwriter",relationshipType="one_to_one")
     */
    protected $underwriter;
    /**
     * @var string
     * @Groups({"Default"})
     * @ORM\Column(type="string", name="policy_number")
     */
    protected $policyNo;
    /**
     * @var \DateTime
     * @Groups({"Default"})
     * @ORM\Column(type="datetime")
     */
    protected $effectiveStartDateTime;
    /**
     * @var \DateTime
     * @Groups({"Default"})
     * @ORM\Column(type="datetime")
     */
    protected $effectiveEndDateTime;
    /**
     * @var string
     * @ORM\Column(type="string",name="policy_status")
     */
    protected $status;
    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $modification;
    /**
     * @var TestContact
     * @Objectiphy\Column(name="contact_id",type="TestContact",relationshipType="one_to_one")
     */
    protected $contact;
    /**
     * @var TestVehicle
     * @Groups({"Default"})
     * @ORM\OneToOne(targetEntity="TestVehicle",mappedBy="policy")
     */
    protected $vehicle;
    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    protected $loginId;

    /**
     * This is a dirty bodge, just for unit test purposes - not recommended
     * for real entities in an application
     * @param $property
     * @return
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
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
