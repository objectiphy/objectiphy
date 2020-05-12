<?php

namespace Objectiphy\Objectiphy\Mapping;

/**
 * An alternative to the Doctrine table annotation (if specified, this will take precedence over Doctrine).
 */
class Table
{
    /** @var string Name of database table */
    public string $name = '';
    
    /**
     * @var string Specifies that a specific repository must be used when loading instances of this class.
     * Use of this attribute is expensive as it requires a separate call to the database for this class when it
     * appears as a child of another class. Only set a value if you really need to (you can still use a custom
     * repository to load this class as a parent, by passing a custom repository name to the repository factory).
     */
    public string $repositoryClassName = '';
}
