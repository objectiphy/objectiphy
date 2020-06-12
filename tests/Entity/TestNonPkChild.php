<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @Objectiphy\Table(name="objectiphy_test.non_pk_child")
 * @property TestUser $user
 * @property string $nebulousIdentifier
 * @property TestParentOfNonPkChild $parent
 */
class TestNonPkChild
{
    /**
     * @var TestUser
     * @Objectiphy\Column(type="TestUser", name="user_id", relationshipType="one_to_one")
     */
    protected $user;
    /**
     * @var string
     * @Objectiphy\Column(type="string")
     */
    protected $nebulousIdentifier;
    /**
     * @var TestParentOfNonPkChild
     * @Objectiphy\Column(type="TestParentOfNonPkChild", name="parent_id", relationshipType="one_to_one")
     */
    protected $parent;
    /**
     * @var TestParentOfNonPkChild
     * @Objectiphy\Column(type="TestParentOfNonPkChild", name="second_parent_id", relationshipType="one_to_one")
     */
    protected $secondParent;
    /**
     * @var TestParentOfNonPkChild
     * @Objectiphy\Column(type="TestParentOfNonPkChild", name="foster_parent_name", relationshipType="many_to_one", joinColumn="name")
     */
    public $fosterParent;


    public function getUser()
    {
        return $this->user;
    }

    public function getNebulousIdentifier()
    {
        return $this->nebulousIdentifier;
    }

    public function setNebulousIdentifier($value)
    {
        $this->nebulousIdentifier = $value;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent(TestParentOfNonPkChild $parent = null)
    {
        $this->parent = $parent;
    }

    public function getSecondParent()
    {
        return $this->secondParent;
    }

    public function setSecondParent(TestParentOfNonPkChild $parent = null)
    {
        $this->secondParent = $parent;
    }
}
