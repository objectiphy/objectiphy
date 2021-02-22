<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Mapping\Column;

/**
 * @Table(name="objectiphy_test.person")
 */
class TestPerson
{
    /**
     * @Column(isPrimaryKey=true)
     */
    public int $id;

    /**
     * @Column
     */
    public string $firstName;

    /**
     * @Column
     */
    public string $lastName;

    /**
     * @Column
     */
    public string $car;

    /**
     * @Column
     */
    public int $year;
}
