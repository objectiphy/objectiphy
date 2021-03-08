<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Mapping information to describe which database table and/or custom repository class to use for storage of the data
 * relating to the properties of the class.
 * The following annotation is just to stop the Doctrine annotation reader complaining if it comes across this.
 * @Annotation
 * @Target("CLASS")
 */
class Table extends ObjectiphyAnnotation
{
    /** @var string Name of database table */
    public string $name = '';
    
    /**
     * @var string Specifies a custom repository to use when loading entities from this table.
     */
    public string $repositoryClassName = '';

    /**
     * @var bool Whether or not to always insist that the custom repository is used - ie. never join to the table
     * to load entities, always use a separate query on the custom repository. Setting this to true will have a
     * negative performance impact (and only takes effect if $repositoryClassName is also set).
     */
    public bool $alwaysLateBind = false;
}
