<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @Mapping\Table(name="objectiphy_test.security_pass")
 * @property int $id
 * @property string $serialNo
 * @property TestEmployee $employee
 */
class TestSecurityPass
{
    /**
     * @var int
     * @Mapping\Column(name="id",isPrimaryKey=true)
     */
    public $id;
    
    /**
     * @var string
     * @Mapping\Column(type="string")
     */
    public $serialNo;
    
    /**
     * @var TestEmployee
     * @Mapping\Relationship(relationshipType="one_to_one", childClassName="TestEmployee")
     */
    public $employee;
}
