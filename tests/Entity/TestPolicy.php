<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;
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
    private $id;
    
    /**
     * @var TestUnderwriter
     * @Mapping\Relationship(sourceJoinColumn="underwriter_id",childClassName="TestUnderwriter",relationshipType="one_to_one")
     * @Groups({"Default"})
     */
    protected $underwriter;
    
    /**
     * @var string
     * @Groups({"Default"})
     * @ORM\Column(type="string", name="policy_number")
     */
    private $policyNo;
    
    /**
     * @var \DateTime
     * @Groups({"Default","PolicyDetails"})
     * @ORM\Column(type="datetime")
     */
    protected $effectiveStartDateTime;
    
    /**
     * @var \DateTime
     * @Groups({"PolicyDetails"})
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
     * @Mapping\Relationship(sourceJoinColumn="contact_id",childClassName="TestContact",relationshipType="one_to_one")
     */
    private $contact;
    
    /**
     * @var TestVehicle
     * @Groups({"Default"})
     * @ORM\OneToOne(targetEntity="TestVehicle",mappedBy="policy",fetch="EAGER")
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
    public function &__get($property)
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
