<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;
use Objectiphy\Objectiphy\Tests\Repository\CustomRepository;

/**
 * @Mapping\Table(name="objectiphy_test.parent", repositoryClassName="CustomRepository", alwaysLateBind=true)
 */
class TestParentCustomRepo
{
    /**
     * @var int
     * @Objectiphy\Objectiphy\Mapping\Groups({"Default"})
     * @Mapping\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @var TestUser
     * @Objectiphy\Objectiphy\Mapping\Groups({"Default"})
     * @Mapping\Relationship(childClassName="TestUser", sourceJoinColumn="user_id", relationshipType="one_to_one", cascadeDeletes=true)
     */
    protected $user;
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups({"Default"})
     * @Mapping\Column(type="string", name="name")
     */
    protected $name;
    /**
     * @var TestChild
     * @Objectiphy\Objectiphy\Mapping\Groups({"Full"})
     * @Mapping\Relationship(childClassName="TestChild", mappedBy="parent", relationshipType="one_to_one")
     */
    protected $child;
    /**
     * @var array
     * @Mapping\Relationship(
     *     childClassName="TestPet",
     *     mappedBy="parent",
     *     relationshipType="one_to_many",
     *     orderBy={"name"="ASC","type"="DESC"},
     *     indexBy="name",
     *     cascadeDeletes=true,
     *     orphanRemoval=true
     * )
     */
    public array $pets = [];
//    /**
//     * var int
//     * @Mapping\Column(aggregateFunctionName="COUNT", aggregateCollection="pets")
//     */
//    public $numberOfPets;
//    /**
//     * @var int
//     * @Mapping\Column(aggregateFunctionName="SUM", aggregateCollection="pets", aggregateProperty="weightInGrams")
//     */
//    public $totalWeightOfPets;
    /**
     * @var datetime
     * @Mapping\Column(name="modified_date_time")
     */
    public $modifiedDateTime;
    /**
     * @Mapping\Relationship(childClassName="TestAddress", relationshipType="one_to_one", isEmbedded=true)
     */
    protected $address;

    /** @var boolean */
    private $nameGetterAccessed = false;
    /** @var boolean */
    private $altNameGetterAccessed = false;
    /** @var boolean */
    private $nameSetterAccessed = false;
    /** @var boolean */
    private $altNameSetterAccessed = false;

    public function getId()
    {
        return $this->id;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(TestUser $value = null)
    {
        $this->user = $value;
    }

    public function getName()
    {
        $this->nameGetterAccessed = true;
        return $this->name;
    }

    public function setName($value)
    {
        $this->nameSetterAccessed = true;
        $this->name = $value;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(TestAddress $value = null)
    {
        $this->address = $value;
    }

    public function getChild()
    {
        return $this->child;
    }

    /**
     * The child owns the relationship, so we need to ensure its parent property is kept consistent with anything
     * we do here.
     * @param TestChild|null $value
     */
    public function setChild(TestChild $value = null)
    {
        if (isset($this->child) && ($value === null || $this->child->getId() != $value->getId())) {
            $this->child->setParent(null); //The current child no longer has $this as its parent
        }

        $this->child = $value;
        if ($value) {
            $this->child->setParent($this);
        }
    }

    public function &getPets()
    {
        $pets = $this->pets ?? null;
        return $pets;
    }

    public function setPets(array $value = null)
    {
        $this->pets = $value;
    }

    public function getNameAlternative()
    {
        $this->altNameGetterAccessed = true;
        return $this->name;
    }

    public function setNameAlternative($value)
    {
        $this->altNameSetterAccessed = true;
        $this->name = $value;
    }

    public function setNameWithOptionalExtraArg($value, $randomJunk = null)
    {
        $this->name = $value;
    }

    public function setNameInvalid($value, $randomJunk)
    {
        $this->name = $value;
    }

    public function wasNameGetterAccessed()
    {
        return $this->nameGetterAccessed;
    }

    public function wasAltNameGetterAccessed()
    {
        return $this->altNameGetterAccessed;
    }

    public function wasNameSetterAccessed()
    {
        return $this->nameSetterAccessed;
    }

    public function wasAltNameSetterAccessed()
    {
        return $this->altNameSetterAccessed;
    }
}
