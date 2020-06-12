<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

/**
 * @property string $positionKey
 * @property string $positionValue
 * @property string $positionDescription
 */
class TestPosition
{
    /**
     * @var string
     * @Objectiphy\Column(name="position_code",type="string")
     */
    public $positionKey;

    /**
     * @var string
     * @Objectiphy\Column(
     *     type="string",
     *     name="objectiphy_test.position.name",
     *     joinTable="objectiphy_test.position",
     *     sourceJoinColumn="position_code",
     *     joinColumn="objectiphy_test.position.value"
     * )
     */
    public $positionValue;

    /**
     * @var string
     * @Objectiphy\Column(
     *     type="string",
     *     name="objectiphy_test.position.description",
     *     joinTable="objectiphy_test.position",
     *     sourceJoinColumn="position_code",
     *     joinColumn="objectiphy_test.position.value"
     * )
     */
    public $positionDescription;
}
