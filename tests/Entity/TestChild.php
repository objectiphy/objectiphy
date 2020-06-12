<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @Objectiphy\Table(name="objectiphy_test.child")
 * @property int $id
 * @property TestUser $user
 * @property string $name
 * @property TestParent $parent
 */
class TestChild
{
    /**
     * @Objectiphy\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @var TestUser
     * @Objectiphy\Column(type="TestUser", name="user_id", relationshipType="one_to_one")
     */
    protected $user;
    /**
     * @var string
     * @Objectiphy\Groups({"Special"})
     * @Objectiphy\Column(type="string", name="name")
     */
    protected $name;
    /**
     * @var string
     * @Objectiphy\Groups({"Special"})
     * @Objectiphy\Column(type="int", name="height_in_cm")
     */
    protected $height;
    /**
     * @var TestParent
     * @Objectiphy\Column(type="TestParent", name="parent_id", relationshipType="one_to_one")
     */
    protected $parent;
    /**
     * @Objectiphy\Column(type="TestAddress", relationshipType="one_to_one", embedded=true, embeddedColumnPrefix="child_")
     */
    public $address;

    public function getId()
    {
        return $this->id;
    }

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
