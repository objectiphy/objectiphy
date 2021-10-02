<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @Mapping\Table(name="objectiphy_test.non_pk_child")
 * @property TestUser $user
 * @property string $nebulousIdentifier
 * @property TestParentOfNonPkChild $parent
 */
class TestNonPkChild
{
    /**
     * @var TestUser
     * @Mapping\Relationship(childClassName="TestUser", sourceJoinColumn="user_id", relationshipType="one_to_one")
     */
    protected $user;
    
    /**
     * @var string
     * @Mapping\Column(type="string")
     */
    protected $nebulousIdentifier;
    
    /**
     * @var TestParentOfNonPkChild
     * @Mapping\Relationship(childClassName="TestParentOfNonPkChild", sourceJoinColumn="parent_id", relationshipType="one_to_one")
     */
    protected $parent;
    
    /**
     * @var TestParentOfNonPkChild
     * @Mapping\Relationship(childClassName="TestParentOfNonPkChild", sourceJoinColumn="second_parent_id", relationshipType="one_to_one")
     */
    protected $secondParent;
    
    /**
     * @var TestParentOfNonPkChild
     * @Mapping\Relationship(childClassName="TestParentOfNonPkChild", sourceJoinColumn="foster_parent_name", relationshipType="many_to_one", targetJoinColumn="name")
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
