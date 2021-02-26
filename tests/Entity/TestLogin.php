<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Mapping\Column;

/**
 * @Table(name="objectiphy_test.login")
 */
class TestLogin
{
    /**
     * @Column(isPrimaryKey=true)
     */
    public $id;

    /**
     * @Column
     */
    public $username;

    /**
     * @Column
     */
    public $password;
}
