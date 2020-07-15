<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;

class ConfigEntity
{
    /**
     * @var string Class name of entity to which these settings relate
     */
    private string $className;

    /**
     * @var array Associative array of custom repository classes keyed on class name (degrades performance). Note that
     * you can also use an annotation for this if you always want a particular entity to use a particular repository
     * class (respositoryClassName attribute on the Objectiphy\Table annotation).
     */
    private string $repositoryClassName;

    /**
     * @var string Overridden database table name
     */
    private string $tableOverride;

    /**
     * @var array Overridden database column names keyed on property name
     */
    private array $columnOverrides;

    /**
     * @var string Name of class to use for collections where one-to-many relationships require a custom collection
     * class rather than a simple array. All -to-many collections for the class will use the specified class. Typically,
     * you should use the collectionType attribute on the relationship mapping information to specify custom a
     * collection class rather than setting it here programatically.
     */
    private string $collectionType;

    /**
     * @var EntityFactoryInterface Factory to use for creating entities. If no factory is supplied, entities will be
     * created directly using the new keyword with no arguments passed to the constructor.
     */
    private EntityFactoryInterface $entityFactory;
}
