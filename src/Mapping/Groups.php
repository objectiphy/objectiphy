<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

use Objectiphy\Annotations\AttributeTrait;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * An alternative to the Symfony or JMS serialization group annotation (if specified, this will take precedence over
 * Symfony and JMS).
 * The following annotation is just to stop the Doctrine annotation reader complaining if it comes across this.
 * @Annotation
 * @Target("PROPERTY")
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Groups
{
    use AttributeTrait;

    /** @var string[] Names of groups */
    public $groups = [];

    public function __construct(array $groups)
    {
        $this->groups = isset($groups['value']) ? $groups['value'] : $groups;
    }
}
