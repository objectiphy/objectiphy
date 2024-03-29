<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Contract\NamingStrategyInterface;
use Objectiphy\Objectiphy\NamingStrategy\PascalCamelToSnake;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * @property bool $devMode
 * @property bool $recordQueries
 * @property string $cacheDirectory
 * @property array $serializationGroups
 * @property bool $hydrateUngroupedProperties
 * @property bool $guessMappings
 * @property NamingStrategyInterface $tableNamingStrategy
 * @property NamingStrategyInterface $columnNamingStrategy
 * @property string $defaultCollectionClass
 * @property bool $allowDuplicates
 * @property bool $eagerLoadToOne
 * @property bool $eagerLoadToMany
 * @property bool $disableDeleteRelationships
 * @property bool $disableDeleteEntities
 * @property string $commonProperty
 * @property string $recordAgeIndicator
 * @property bool $bindToEntities
 * @property bool $saveChildrenByDefault
 * @property bool $disableEntityCache
 * @property int $maxDepth
 */
class ConfigOptions extends ConfigBase
{
    public const DEV_MODE = 'devMode';
    public const RECORD_QUERIES = 'recordQueries';
    public const CACHE_DIRECTORY = 'cacheDirectory';
    public const SERIALIZATION_GROUPS = 'serializationGroups';
    public const HYDRATE_UNGROUPED_PROPERTIES = 'hydrateUngroupedProperties';
    public const GUESS_MAPPINGS = 'guessMappings';
    public const TABLE_NAMING_STRATEGY = 'tableNamingStrategy';
    public const COLUMN_NAMING_STRATEGY = 'columnNamingStrategy';
    public const DEFAULT_COLLECTION_CLASS = 'defaultCollectionClass';
    public const ALLOW_DUPLICATES = 'allowDuplicates';
    public const EAGER_LOAD_TO_ONE = 'eagerLoadToOne';
    public const EAGER_LOAD_TO_MANY = 'eagerLoadToMany';
    public const DISABLE_DELETE_RELATIONSHIPS = 'disableDeleteRelationships';
    public const DISABLE_DELETE_ENTITIES = 'disableDeleteEntities';
    public const COMMON_PROPERTY = 'commonProperty';
    public const RECORD_AGE_INDICATOR = 'recordAgeIndicator';
    public const BIND_TO_ENTITIES = 'bindToEntities';
    public const ENTITY_CONFIG = 'entityConfig';
    public const SAVE_CHILDREN_BY_DEFAULT = 'saveChildrenByDefault';
    public const DISABLE_ENTITY_CACHE = 'disableEntityCache';
    public const MAX_DEPTH = 'maxDepth';
    public const MAPPING_DIRECTORY = 'mappingDirectory';
    
    /**
     * @var bool Whether or not we are running in debug mode (proxy classes get rebuilt on each run).
     */
    protected bool $devMode;

    /**
     * @var bool Whether or not to record every query that is run (for debugging purposes). If null, this will default 
     * to true when running in dev mode, and false when in production. Memory usage will be lower if this is false.
     */
    protected ?bool $recordQueries = null;
    
    /**
     * @var string Directory in which to store cache and proxy class files.
     */
    protected string $cacheDirectory;

    /**
     * @var array Indexed array of serialization group names. If specified, only properties that belong to the
     * specified group(s) will be hydrated. Objectiphy does not do any serialization, this just allows you to improve
     * performance by only hydrating properties that are going to be serialized in your application. Supports
     * Symfony serialization groups, JMS Serializer groups, and Objectiphy has its own groups annotation if you are not
     * using either of those.
     */
    protected array $serializationGroups = [];

    /**
     * @var bool Whether or not to hydrate properties that do not have a serialization group. Only applicable if one or
     * more serialization groups have been specified.
     */
    protected bool $hydrateUngroupedProperties = true;

    /**
     * @var bool Whether or not to use a naming strategy to guess the table and column names based on the class and
     * property names of your entities, where an explicit name has not been supplied. Defaults to true, using the
     * PascalCase or camelCase to snake_case naming strategy for both.
     */
    protected bool $guessMappings = true;

    /**
     * @var NamingStrategyInterface Naming strategy to use for converting entity class names to table names. Defaults
     * to PascalCase or camelCase to snake_case conversion.
     */
    protected NamingStrategyInterface $tableNamingStrategy;

    /**
     * @var NamingStrategyInterface Naming strategy to use for converting property names to column names. Defaults to
     * PascalCase or camelCase to snake_case conversion.
     */
    protected NamingStrategyInterface $columnNamingStrategy;

    /**
     * @var string Name of class to use by default for collections in one-to-many or many-to-many relationships. You 
     * would only use this if you want all of your collections to use the same collection class by default. You can 
     * override the value for specific parent classes using the collectionClass entity config option, or for indiviual
     * properties by using the collectionClass mapping attribute (typically via an annotation). If not specified (or
     * specified as 'array'), a simple PHP array will be used. If you need to use a custom factory to create your 
     * collection instances, pass your factory to RepositoryFactory before you create any repositories.
     */
    protected string $defaultCollectionClass = 'array';
    
    /**
     * @var bool Whether or not to allow duplicate entities to be returned.
     */
    protected bool $allowDuplicates = false;

    /**
     * @var bool Whether to load one-to-one and many-to-one relationships immediately (typically using SQL joins).
     */
    protected ?bool $eagerLoadToOne = null;

    /**
     * @var bool Whether to load one-to-many and many-to-many relationships immediately (requires a separate query
     * regardless, but if eager loading, the separate query is run straight away, it doesn't wait for the property to
     * be accessed).
     */
    protected bool $eagerLoadToMany = false;

    /**
     * @var bool Whether to disable deleting relationships (setting foreign key values to null). For performance and
     * safety reasons, it is recommended to set this to true, and only set it to false when you need to delete a
     * relationship. Defaults to false because disabling the removal of child entities is not what one would
     * intuitively expect.
     */
    protected bool $disableDeleteRelationships = false;

    /**
     * @var bool Whether to disable deleting entities. For performance and safety reasons, it is recommended to set
     * this to true, and only set it to false when you need to delete an entity. Defaults to false because disabling
     * the deletion of entities is not what one would intuitively expect.
     */
    protected bool $disableDeleteEntities = false;

    /**
     * @var string|null When returning the latest record from each group of results, this property determines which
     * value to group by.
     */
    protected ?string $commonProperty = null;

    /**
     * @var string|null When returning the latest record from each group of results, this property determines how to
     * identify which record is the latest. Unlike other settings, this one is based on database column names, NOT
     * property names, because it allows you to use SQL expressions, and database columns that are not mapped to entity
     * properties.
     */
    protected ?string $recordAgeIndicator = null;

    /**
     * @var bool Whether or not to hydrate entities with the data returned. If false, a plain array of data will be
     * returned.
     */
    protected bool $bindToEntities = true;

    /**
     * @var array Entity specific configuration options (ConfigEntity instances), keyed by entity class name.
     */
    protected array $entityConfig = [];

    /**
     * @var bool Whether or not to save child entities when a parent entity is saved. You can also set this on a 
     * case by case basis using a flag at the time you call the saveEntity method.
     */
    protected bool $saveChildrenByDefault = true;

    /**
     * @var bool Whether to stop tracking entities and always refresh from the database. Setting this to true will
     * cause performance degradation.
     */
    protected bool $disableEntityCache = false;

    /**
     * @var int Maximum depth of hierarchy to load. Anything beyond this level will be lazy loaded, even if it is
     * set to eager load normally. If zero, there is no limit to the depth - in that case, it will continue to load
     * the entire hierarchy until it hits recursion, a lazy load setting, runs out of memory, or hits the database
     * join limit. That should be fine unless you have a very deep hierarchy, but you might find performance benefits
     * from setting $maxDepth to a non-zero value - it depends what you do with the results, ie. whether or not you
     * intend to access every property in the entire hierarchy (for example by serializing the result).
     */
    protected int $maxDepth = 3;

    /**
     * @var string Directory to scan for mapping files which can override or apply mapping information to entities.
     * All valid mapping files in the given directory will be loaded - one file per entity (this is a convenience
     * setting to allow multiple mapping override files to be applied at once rather than having to set them for each
     * entity individually). Any .json file with a property named 'class' will be processed. Any other files will be
     * ignored. Sub-directories will be traversed so you can organise mapping files as you wish, but avoid using a
     * parent directory which has other unrelated directories beneath it or it will slow things down.
     */
    protected string $mappingDirectory = '';

    /**
     * Initialise config options.
     * @param array $options Array of config options to set
     * @param string $configFile Location of config file that contains the default options
     * @throws ObjectiphyException
     */
    public function __construct(
        array $options = [],
        string $configFile = ''
    ) {
        $this->devMode = $options['devMode'] = boolval($options['devMode'] ?? true);
        $this->parseConfigFile($configFile);
        $options = $options ?: ['cacheDirectory' => '', 'devMode' => true];
        $this->setInitialOptions($options);
        $this->setCacheDirectory($options['cacheDirectory'] ?? $this->cacheDirectory ?? '');
    }

    public function __clone()
    {
        foreach ($this->entityConfig ?? [] as $key => $entityConfig) {
            $this->entityConfig[$key] = clone($entityConfig);
        }
    }

    public function setMappingDirectory(string $value)
    {
        if (!is_dir($value)) {
            throw new ObjectiphyException('Mapping directory does not exist' . (!$this->devMode ? '.' : ' (' . $cacheDirectory . ').'));
        }

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($value, \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if ($file->isFile() && $file->getExtension() == 'json') {
                $contents = json_decode(file_get_contents($file->getPathname()), true);
                if ($entityClassName = $contents['className'] ?? false) {
                    $entityConfig = $this->entityConfig[$entityClassName] ?? new ConfigEntity($entityClassName);
                    $entityConfig->setConfigOption(ConfigEntity::MAPPING_FILE, $file->getPathname());
                    $this->entityConfig[$entityClassName] = $entityConfig;
                }
            }
        }
    }

    /**
     * @param string $cacheDirectory
     * @throws ObjectiphyException
     */
    private function setCacheDirectory(string $cacheDirectory): void
    {
        if ($cacheDirectory) {
            $usedCacheDir = (!$this->devMode ? '.' : ' (' . $cacheDirectory . ').');
            if (!is_dir($cacheDirectory)) {
                try {
                    mkdir($cacheDirectory, 0777, true);
                } catch (\Throwable $ex) {}
            }
            if (!is_dir($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy cache directory does not exist' . $usedCacheDir);
            } elseif (!is_writable($cacheDirectory)) {
                throw new ObjectiphyException('Objectiphy cache directory is not writable' . $usedCacheDir);
            } else {
                $this->cacheDirectory = $cacheDirectory;
            }
        } elseif (!$this->devMode) {
            throw new ObjectiphyException('You must specify a cache directory for Objectiphy when not running in debug mode.');
        } else {
            $this->cacheDirectory = sys_get_temp_dir(); //Not safe in production due to garbage collection
        }
    }

    /**
     * @param string $configFile
     * @throws ObjectiphyException
     */
    private function parseConfigFile(string $configFile): void
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
    private function setInitialOptions(array $options): void
    {
        foreach ($options ?? [] as $key => $value) {
            if (!is_string($key)) {
                throw new ObjectiphyException('Config option key must be a string - use a associative array for initial config options.');
            }
            $this->setConfigOption($key, $value);
        }
        
        if (!array_key_exists('tableNamingStrategy', $options) && empty($this->tableNamingStrategy)) {
            $this->tableNamingStrategy = new PascalCamelToSnake();
        }
        
        if (!array_key_exists('columnNamingStrategy', $options) && empty($this->columnNamingStrategy)) {
            $this->columnNamingStrategy = new PascalCamelToSnake();
        }
    }
}
