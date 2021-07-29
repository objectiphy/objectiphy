<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * An alternative to the Symfony or JMS serialization group annotation (if specified, this will take precedence over
 * Symfony and JMS).
 * The following annotation is just to stop the Doctrine annotation reader complaining if it comes across this.
 * @Annotation
 * @Target("PROPERTY")
 */
class Groups
{
    /** @var string[] Names of groups */
    public $groups = [];

    public function __construct(array $groups)
    {
        $this->groups = isset($groups['value']) ? $groups['value'] : $groups;
    }
}
