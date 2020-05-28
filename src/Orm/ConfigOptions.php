<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\EntityFactoryInterface;
use Objectiphy\Objectiphy\NamingStrategy\PascalCamelToSnake;

/**
 * @property bool $productionMode
 * @property string $cacheDirectory
 * @property array $customRepositoryClasses
 * @property array $tableOverrides
 * @property array $columnOverrides
 * @property array $serializationGroups
 * @property bool $hydrateUngroupedProperties
 * @property bool $guessMappings
 * @property NamingStrategyInterface $tableNamingStrategy
 * @property NamingStrategyInterface $columnNamingStrategy
 * @property array $collectionTypes
 * @property bool $allowDuplicates
 * @property bool $eagerLoadToOne
 * @property bool $eagerLoadToMany
 * @property bool $disableDeleteRelationships
 * @property bool $disableDeleteEntities
 * @property string $commonProperty
 * @property string $recordAgeIndicator
 * @property bool $bindToEntities
 * @property array $queryOverrides
 * @property array $entityFactories
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class ConfigOptions
{
    /**
     * @var bool Whether or not we are running in production (proxy classes do not get rebuilt on each run).
     */
    private bool $productionMode;
    
    /**
     * @var string Directory in which to store cache and proxy class files.
     */
    private string $cacheDirectory;
    
    /**
     * @var array Associative array of custom repository classes keyed on class name (degrades performance). Note that
     * you can also use an annotation for this if you always want a particular entity to use a particular repository
     * class (respositoryClassName attribute on the Objectiphy\Table annotation).
     */
    private array $customRepositoryClasses = [];
    
    /**
     * @var array Associative array of overridden database table names keyed on class name, eg.:
     * ["MyNamespace\MyEntity" => "my_entity_table"]
     */
    private array $tableOverrides = [];
    
    /**
     * @var array Associative array of overridden database column names. This is a nested array, the first level is
     * keyed on class name, and the second level is keyed on property name, eg.:
     * ["MyNamespace\MyEntity" => ["idProperty" => "identifier_column"]]
     */
    private array $columnOverrides = [];
    
    /**
     * @var array Indexed array of serialization group names. If specified, only properties that belong to the
     * specified group(s) will be hydrated. Objectiphy does not do any serialization, this just allows you to improve
     * performance by only hydrating properties that are going to be serialized in your application. Supports
     * Symfony serialization groups, JMS Serializer groups, and Objectiphy has its own groups annotation if you are not
     * using either of those.
     */
    private array $serializationGroups = [];

    /**
     * @var bool Whether or not to hydrate properties that do not have a serialization group. Only applicable if one or
     * more serialization groups have been specified.
     */
    private bool $hydrateUngroupedProperties = true;

    /**
     * @var bool Whether or not to use a naming strategy to guess the table and column names based on the class and
     * property names of your entities, where an explicit name has not been supplied. Defaults to true, using the
     * PascalCase or camelCase to snake_case naming strategy for both.
     */
    private bool $guessMappings = true;

    /**
     * @var NamingStrategyInterface Naming strategy to use for converting entity class names to table names. Defaults
     * to PascalCase or camelCase to snake_case conversion.
     */
    private NamingStrategyInterface $tableNamingStrategy;

    /**
     * @var NamingStrategyInterface Naming strategy to use for converting property names to column names. Defaults to
     * PascalCase or camelCase to snake_case conversion.
     */
    private NamingStrategyInterface $columnNamingStrategy;

    /**
     * @var string Name of class to use by default for collections in one-to-many or many-to-many relationships. You 
     * would only use this if you want all of your collections to use the same collection class by default. You can 
     * override the value for specific parent classes using the collectionTypes config option, or for indiviual 
     * properties by using the collectionType mapping attribute (typically via an annotation). If not specified (or 
     * specified as 'array'), a simple PHP array will be used.
     */
    private string $defaultCollectionType = 'array';
    
    /**
     * @var array Associative array of collection classes where one-to-many relationships require a custom collection
     * class rather than a simple array. Keyed on class name (all -to-many collections for the class will use the
     * specified class. Typically, you should use the collectionType attribute on the relationship mapping information
     * to specify custom a collection class rather than setting it here programatically.
     */
    private array $collectionTypes = [];

    /**
     * @var bool Whether or not to allow duplicate entities to be returned.
     */
    private bool $allowDuplicates = false;

    /**
     * @var bool Whether to load one-to-one and many-to-one relationships immediately (typically using SQL joins).
     */
    private bool $eagerLoadToOne = true;

    /**
     * @var bool Whether to load one-to-many and many-to-many relationships immediately (requires a separate query
     * regardless, but if eager loading, the separate query is run straight away, it doesn't wait for the property to
     * be accessed).
     */
    private bool $eagerLoadToMany = false;

    /**
     * @var bool Whether to disable deleting relationships (setting foreign key values to null). For performance and
     * safety reasons, it is recommended to set this to true, and only set it to false when you need to delete a
     * relationship. Defaults to false because disabling the removal of child entities is not what one would
     * intuitively expect.
     */
    private bool $disableDeleteRelationships = false;

    /**
     * @var bool Whether to disable deleting entities. For performance and safety reasons, it is recommended to set
     * this to true, and only set it to false when you need to delete an entity. Defaults to false because disabling
     * the deletion of entities is not what one would intuitively expect.
     */
    private bool $disableDeleteEntities = false;

    /**
     * @var string|null When returning the latest record from each group of results, this property determines which
     * value to group by.
     */
    private ?string $commonProperty = null;

    /**
     * @var string|null When returning the latest record from each group of results, this property determines how to
     * identify which record is the latest. Unlike other settings, this one is based on database column names, NOT
     * property names, because it allows you to use SQL expressions, and database columns that are not mapped to entity
     * properties.
     */
    private ?string $recordAgeIndicator = null;

    /**
     * @var bool Whether or not to hydrate entities with the data returned. If false, a plain array of data will be
     * returned.
     */
    private bool $bindToEntities = true;

    /**
     * @var array Associative array of SQL snippets to override what gets executed, keyed by one of the following
     * values:  select, from, joinsForLatestRecord, joins, where, groupBy, having, orderBy, limit, insert, update.
     * Try to minimise using this, so as to keep your code de-coupled from your database.
     */
    private array $queryOverrides = [];

    /**
     * @var EntityFactoryInterface[]|null Associative array of factories to use for creating entities, keyed by class
     * name. Any factory class supplied must implement EntityFactoryInterface. If no factories are supplied, entities 
     * will be created directly using the new keyword with no arguments passed to the constructor. Note that this array
     * should contain concrete instances of factories, not just the factory class name!
     */
    private array $entityFactories = [];

    /**
     * @var array Keep track of which options have changed from the default value so that we can cache mappings for a
     * particular configuration.
     */
    private array $nonDefaults = [];

    /**
     * Initialise config options.
     * @param string $cacheDirectory
     * @param bool $productionMode
     * @param string $configFile Location of config file that contains the default options
     * @param array $options Array of config options to set
     * @throws ObjectiphyException
     */
    public function __construct(string $configFile = '', array $options = ['cacheDirectory' => '', 'productionMode' => false])
    {
        $this->setCacheDirectory($options['cacheDirectory'] ?? '');
        $this->setInitialOptions($options);
        $this->parseConfigFile($configFile);
    }

    /**
     * For convenience, you can get config options as properties instead of using the generic getter
     * @param $optionName
     * @return mixed
     * @throws ObjectiphyException
     */
    public function __get($optionName)
    {
        return $this->getConfigOption($optionName);
    }

    /**
     * For convenience, you can set config options as properties instead of using the generic setter
     * @param $optionName
     * @param $value
     * @throws ObjectiphyException
     */
    public function __set($optionName, $value)
    {
        $this->setConfigOption($optionName, $value);
    }

    /**
     * Set a config option.
     * @param string $optionName
     * @param $value
     * @throws ObjectiphyException
     */
    public function setConfigOption(string $optionName, $value)
    {
        if (property_exists($this, $optionName)) {
            $this->{$optionName} = $value;
            $this->nonDefaults[$optionName] = $value;
        } else {
            $this->throwNotExists($optionName);
        }
    }

    /**
     * Get a config option, if it exists.
     * @param string $optionName
     * @return mixed
     * @throws ObjectiphyException
     */
    public function getConfigOption(string $optionName)
    {
        if (property_exists($this, $optionName)) {
            return $this->{$optionName};
        } else {
            $this->throwNotExists($optionName);
        }
    }

    /**
     * Safely set an individual element of a config option that is an array.
     * @param string $optionName
     * @param string $key
     * @param $value
     * @throws ObjectiphyException
     */
    public function setConfigArrayOption(string $optionName, string $key, $value)
    {
        $this->validateArray($optionName);
        $this->{$optionName}[$key] = $value;
        $this->nonDefaults[$optionName] = $this->{$optionName};
    }

    /**
     * Safely get an individual element of a config option that is an array.
     * @param string $optionName
     * @param string $key
     * @return |null
     * @throws ObjectiphyException
     */
    public function getConfigArrayOption(string $optionName, string $key)
    {
        $this->validateArray($optionName);

        return $this->{$optionName}[$key] ?? null;
    }

    /**
     * Returns a hash uniquely representing the config options that are currently set (to be used as a cache key for 
     * mapping information that uses this particular set of config options).
     */
    public function getHash()
    {
        ksort($this->nonDefaults);
        return sha1(serialize($this->nonDefaults));
    }

    /**
     * @param string $cacheDirectory
     * @throws ObjectiphyException
     */
    private function setCacheDirectory(string $cacheDirectory)
    {
        if ($cacheDirectory) {
            if (!file_exists($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy cache directory does not exist' . ($productionMode ? '.' : ' (' . $cacheDirectory . ').'));
            } elseif (!is_writable($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy cache directory is not writable' . ($productionMode ? '.' : ' (' . $cacheDirectory . ').'));
            } else {
                $this->cacheDirectory = $cacheDirectory;
            }
        } elseif ($productionMode) {
            throw new ObjectiphyException('You must specify a cache directory for Objectiphy when running in production mode.');
        } else {
            $this->cacheDirectory = sys_get_temp_dir(); //Not safe in production due to garbage collection
        }
    }

    /**
     * @param string $configFile
     * @throws ObjectiphyException
     */
    private function parseConfigFile(string $configFile)
    {
        if ($configFile && !file_exists($configFile)) {
            throw new ObjectiphyException(sprintf('The config file specified does not exist: %1$s.', $configFile));
        } elseif ($configFile) {
            $defaultConfig = parse_ini_file($configFile, false, \INI_SCANNER_TYPED);
            if (!$defaultConfig) {
                throw new ObjectiphyException(sprintf('The config file specified could not be parsed - please check the syntax: %1$s', $configFile));
            }
            foreach ($defaultConfig as $configKey => $configValue) {
                if (property_exists($this, $configKey)) {
                    // As we are using this as the base set of default options, we bypass
                    // the setter (no need to record these values as changed)
                    $this->{$configKey} = $configValue;
                }
            }
        }
    }

    /**
     * @param array $options
     * @throws ObjectiphyException
     */
    private function setInitialOptions(array $options)
    {
        foreach ($options ?? [] as $key => $value) {
            $this->setConfigOption($key, $value);
        }
        
        if (!array_key_exists('tableNamingStrategy', $options) && empty($this->tableNamingStrategy)) {
            $this->tableNamingStrategy = new PascalCamelToSnake();
        }
        
        if (!array_key_exists('columnNamingStrategy', $options) && empty($this->columnNamingStrategy)) {
            $this->columnNamingStrategy = new PascalCamelToSnake();
        }
    }

    /**
     * @param string $optionName
     * @throws ObjectiphyException
     */
    private function validateArray(string $optionName)
    {
        if (!property_exists($this, $optionName)) {
            $this->throwNotExists($optionName);
        } elseif (!is_array($this->{$optionName})) {
            throw new ObjectiphyException(sprintf('Config option %1$s is not an array.', $optionName));
        }
    }

    /**
     * @param string $optionName
     * @throws ObjectiphyException
     */
    private function throwNotExists(string $optionName)
    {
        throw new ObjectiphyException(sprintf('Config option %1$s does not exist.', $optionName));
    }
}
