<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping\Table;
use Objectiphy\Objectiphy\Mapping\Column;
use Objectiphy\Objectiphy\Mapping\Relationship;

/**
 * This class is just used to ensure the example queries in the documentation would work, so is not meant to be
 * realistic in any way!
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

    /** @Column */
    public string $postcode;

    /** @Column */
    public string $email;

    /**
     * @Relationship(childClassName="TestContact",sourceJoinColumn="contact_id",relationshipType="one_to_one")
     */
    public TestContact $contact;

    /**
     * @Relationship(childClassName="TestLogin",sourceJoinColumn="login_id",relationshipType="one_to_one")
     */
    public TestContact $login;
}
