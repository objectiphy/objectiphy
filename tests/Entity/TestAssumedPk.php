<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @Mapping\Table(name="objectiphy_test.assumed_pk")
 */
class TestAssumedPk
{
    /**
     * @var int
     * @Mapping\Column(name="id")
     */
    public $id;

    /**
     * @var string
     * @Mapping\Column(type="string", name="name")
     */
    public $name;

    /**
     * @Mapping\Relationship(
     *     childClassName="TestPet",
     *     mappedBy="parent",
     *     relationshipType="one_to_many",
     *     orderBy={"name"="ASC","type"="DESC"},
     *     cascadeDeletes=true,
     *     orphanRemoval=true
     * )
     */
    public $pets;
}
