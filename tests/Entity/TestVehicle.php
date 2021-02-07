<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping AS ORM;
use Objectiphy\Objectiphy\Mapping\Column;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="objectiphy_test.vehicle")
 * @property int $id
 * @property string $abiCode
 * @property string $regNo
 * @property string $makeDesc
 * @property string $modelDesc
 * @property TestPolicy $policy
 * @property TestTelematicsBox $telematicsBox
 * @property array<TestWheel> $wheels
 * @property string $type
 */
class TestVehicle
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     * @Groups({"Default"})
     * @ORM\Column(type="string",name="abi_code")
     */
    protected $abiCode;
    
    /**
     * @Groups({"Default"})
     * @ORM\Column(type="string",name="reg_no")
     */
    protected $regNo;
    
    /**
     * @Groups({"Default"})
     * @ORM\Column(type="string",name="make")
     */
    protected $makeDesc;
    
    /**
     * @Groups({"Default"})
     * @ORM\Column(type="string",name="model")
     */
    protected $modelDesc;
    
    /**
     * @var TestPolicy
     * @ORM\OneToOne(targetEntity="TestPolicy",inversedBy="vehicle")
     * @ORM\JoinColumn(name="policy_id")
     */
    protected $policy;
    
    /**
     * @var TestTelematicsBox
     * @ORM\OneToOne(targetEntity="TestTelematicsBox",mappedBy="vehicle")
     */
    protected $telematicsBox;
    
    /**
     * @var TestCollection $wheels
     * @ORM\OneToMany(targetEntity="TestWheel",mappedBy="vehicle")
     */
    protected TestCollection $wheels;

    /**
     * @var string $type
     * @Column(name="type")
     */
    protected string $type;

    /**
     * @var int
     * @Column(name="owner_contact_id")
     */
    protected int $ownerContactId;

    //This property is unmapped and used to test that a factory can be used even when we need a proxy
    public $factoryTest = '';

    public function __construct($factoryTestMessage = '')
    {
        $this->factoryTest = $factoryTestMessage;
    }

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
}
