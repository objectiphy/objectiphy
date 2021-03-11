<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @Mapping\Table(name="objectiphy_test.child")
 * @property int $id
 * @property TestUser $user
 * @property string $name
 * @property TestParent $parent
 */
class TestChildCustomParentRepo
{
    /**
     * @Mapping\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @var TestUser
     * @Mapping\Relationship(childClassName="TestUser", sourceJoinColumn="user_id", relationshipType="one_to_one")
     */
    protected $user;
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups({"Special"})
     * @Mapping\Column(type="string", name="name")
     */
    protected $name;
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups({"Special"})
     * @Mapping\Column(type="int", name="height_in_cm")
     */
    protected $height;
    /**
     * @var TestParentCustomRepo
     * @Mapping\Relationship(childClassName="TestParentCustomRepo", sourceJoinColumn="parent_id", relationshipType="one_to_one")
     */
    protected $parent;
    /**
     * @Mapping\Relationship(childClassName="TestAddress", relationshipType="one_to_one", isEmbedded=true, embeddedColumnPrefix="child_")
     */
    public $address;

    public function getUser()
    {
        return $this->user;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setHeight($value)
    {
        $this->height = $value;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent(TestParent $parent = null)
    {
        $this->parent = $parent;
    }
}
