<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @Objectiphy\Table(name="objectiphy_test.security_pass")
 * @property int $id
 * @property string $serialNo
 * @property TestEmployee $employee
 */
class TestSecurityPass
{
    /**
     * @var int
     * @Objectiphy\Column(name="id",isPrimaryKey=true)
     */
    public $id;
    /**
     * @var string
     * @Objectiphy\Column(type="string")
     */
    public $serialNo;
    /**
     * @var TestEmployee
     * @Objectiphy\Column(relationshipType="one_to_one", type="TestEmployee")
     */
    public $employee;
}
