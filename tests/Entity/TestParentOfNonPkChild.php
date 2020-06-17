<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @Mapping\Table(name="objectiphy_test.parent_of_non_pk_child")
 * @property int $id
 * @property TestUser $user
 * @property string $name
 * @property TestNonPkChild $child
 */
class TestParentOfNonPkChild
{
    /**
     * @var int
     * @Mapping\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @var string
     * @Mapping\Column(type="string", name="name")
     */
    protected $name;
    /**
     * @var TestNonPkChild
     * @Mapping\Relationship(childClassName="TestNonPkChild", mappedBy="parent", relationshipType="one_to_one")
     */
    protected $child;
    /**
     * @var TestNonPkChild
     * @Mapping\Relationship(childClassName="TestNonPkChild", mappedBy="secondParent", relationshipType="one_to_one")
     */
    protected $secondChild;
    /**
     * @var TestNonPkChild
     * @Mapping\Relationship(childClassName="TestNonPkChild", mappedBy="fosterParent", relationshipType="one_to_many", orderBy={"nebulousIdentifier"="DESC"})
     */
    public $fosterKids;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getChild()
    {
        return $this->child;
    }

    public function getSecondChild()
    {
        return $this->secondChild;
    }

    /**
     * The child owns the relationship, so we need to ensure its parent property is kept consistent with anything
     * we do here.
     * @param TestNonPkChild $value
     */
    public function setChild(TestNonPkChild $value = null)
    {
        if (isset($this->child) && ($value === null || $this->child->getNebulousIdentifier() != $value->getNebulousIdentifier())) {
            $this->child->setParent(null); //The current child no longer has $this as its parent
        }

        $this->child = $value;
        if ($value) {
            $this->child->setParent($this);
        }
    }
}
