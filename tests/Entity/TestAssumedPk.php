<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @Objectiphy\Table(name="objectiphy_test.assumed_pk")
 */
class TestAssumedPk
{
    /**
     * @var int
     * @Objectiphy\Column(name="id")
     */
    public $id;
    /**
     * @var string
     * @Objectiphy\Column(type="string", name="name")
     */
    public $name;
    /**
     * @Objectiphy\Column(type="TestPet", mappedBy="parent", relationshipType="one_to_many", orderBy={"name"="ASC","type"="DESC"}, cascadeDeletes=true, orphanRemoval=true)
     */
    public $pets;
}
