<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

/**
 * @property string $positionKey
 * @property string $positionValue
 * @property string $positionDescription
 */
class TestPosition
{
    /**
     * @var string
     * @Mapping\Column(name="position_code",type="string")
     */
    public $positionKey;

    /**
     * @var string
     * @Mapping\Relationship(
     *     relationshipType="scalar",
     *     type="string",
     *     targetScalarValueColumn="objectiphy_test.position.name",
     *     joinTable="objectiphy_test.position",
     *     sourceJoinColumn="position_code",
     *     targetJoinColumn="objectiphy_test.position.value"
     * )
     */
    public $positionValue;

    /**
     * @var string
     * @Mapping\Relationship(
     *     relationshipType="scalar",
     *     type="string",
     *     targetScalarValueColumn="objectiphy_test.position.description",
     *     joinTable="objectiphy_test.position",
     *     sourceJoinColumn="position_code",
     *     targetJoinColumn="objectiphy_test.position.value"
     * )
     */
    public $positionDescription;
}
