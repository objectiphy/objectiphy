<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Mapping\Column;

/**
 * @Table(name="objectiphy_test.department")
 */
class TestDepartment
{
    /**
     * @Column(isPrimaryKey=true)
     */
    public $id;

    /**
     * @Columnn(type="string")
     */
    public $name;
}
