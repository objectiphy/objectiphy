<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Mapping;

/**
 * Mapping information to describe which database table and/or custom repository class to use for storage of the data
 * relating to the properties of the class.
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
