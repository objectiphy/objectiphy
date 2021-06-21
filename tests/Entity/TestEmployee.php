<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @Mapping\Table(name="objectiphy_test.employee")
 * @property int $id
 * @property string $name
 * @property TestEmployee $mentor
 * @property TestEmployee $mentee
 * @property TestEmployee $unionRep
 */
class TestEmployee
{
    /**
     * @var int
     * @Mapping\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @var string
     * @Mapping\Column(type="string")
     */
    protected $name;
    /**
     * @var TestEmployee
     * @Mapping\Relationship(childClassName="TestEmployee", sourceJoinColumn="mentor_id", relationshipType="one_to_one")
     */
    protected $mentor;
    /**
     * @var TestEmployee
     * @Mapping\Relationship(childClassName="TestEmployee", sourceJoinColumn="mentee_id", relationshipType="one_to_one")
     */
    protected $mentee;
    /**
     * @var TestEmployee
     * @Mapping\Relationship(childClassName="TestEmployee", sourceJoinColumn="union_rep_id", relationshipType="many_to_one", orphanRemoval=true)
     */
    protected $unionRep;
    /**
     * @var TestEmployee[]
     * @Mapping\Relationship(childClassName="TestEmployee", relationshipType="one_to_many", mappedBy="unionRep")
     */
    protected $unionMembers;
    /**
     * @var TestPosition
     * @Mapping\Relationship(childClassName="TestPosition",relationshipType="one_to_one",isEmbedded=true)
     */
    protected $position;

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
