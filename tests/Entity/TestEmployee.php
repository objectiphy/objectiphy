<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @Objectiphy\Table(name="objectiphy_test.employee")
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
     * @Objectiphy\Column(isPrimaryKey=true)
     */
    protected $id;
    /**
     * @var string
     * @Objectiphy\Column(type="string")
     */
    protected $name;
    /**
     * @var TestEmployee
     * @Objectiphy\Column(type="TestEmployee", name="mentor_id", relationshipType="one_to_one")
     */
    protected $mentor;
    /**
     * @var TestEmployee
     * @Objectiphy\Column(type="TestEmployee", name="mentee_id", relationshipType="one_to_one")
     */
    protected $mentee;
    /**
     * @var TestEmployee
     * @Objectiphy\Column(type="TestEmployee", name="union_rep_id", relationshipType="one_to_one")
     */
    protected $unionRep;
    /**
     * @var TestEmployee[]
     * @Objectiphy\Column(type="TestEmployee", relationshipType="one_to_many", mappedBy="unionRep")
     */
    protected $unionMembers;
    /**
     * @var TestPosition
     * @Objectiphy\Column(type="TestPosition",relationshipType="one_to_one",embedded=true)
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
