<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;

/**
 * @property string $className
 * @property string $repositoryClassName
 * @property string $tableOverride
 * @property string[] $columnOverrides
 * @property string $collectionType
 * @property EntityFactoryInterface $entityFactory
 */
class ConfigEntity extends ConfigBase
{
    /**
     * @var string Class name of entity to which these settings relate
     */
    protected string $className;

    /**
     * @var array Custom repository class for this entity (degrades performance). Note that you can also use an
     * annotation (or other mapping directive) for this if you always want a particular entity to use a particular
     * repository class (respositoryClassName attribute on the Objectiphy\Table annotation).
     */
    protected string $repositoryClassName;

    /**
     * @var string Overridden database table name
     */
    protected string $tableOverride;

    /**
     * @var string[] Overridden database column names keyed on property name
     */
    protected array $columnOverrides;

    /**
     * @var string Name of class to use for collections where one-to-many relationships require a custom collection
     * class rather than a simple array. All -to-many collections for the class will use the specified class. Typically,
     * you should use the collectionType attribute on the relationship mapping information to specify a custom
     * collection class rather than setting it here programatically.
     */
    protected string $collectionType;

    /**
     * @var EntityFactoryInterface Factory to use for creating entities. If no factory is supplied, entities will be
     * created directly using the new keyword with no arguments passed to the constructor.
     */
    protected EntityFactoryInterface $entityFactory;
}
