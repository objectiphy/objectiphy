<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Config;

use Objectiphy\Objectiphy\Contract\CollectionFactoryInterface;
use Objectiphy\Objectiphy\Contract\EntityFactoryInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * @property string $className
 * @property string $repositoryClassName
 * @property string $tableOverride
 * @property string[] $columnOverrides
 * @property string $collectionClass
 * @property EntityFactoryInterface $entityFactory
 */
class ConfigEntity extends ConfigBase
{
    public const TABLE_OVERRIDES = 'tableOverrides';
    public const COLUMN_OVERRIDES = 'columnOverrides';
    public const RELATIONSHIP_OVERRIDES = 'relationshipOverrides';
    public const COLLECTION_CLASS = 'collectionClass';
    public const ENTITY_FACTORY = 'entityFactory';
    public const MAPPING_FILE = 'mappingFile';

    /**
     * @var string Class name of entity to which these settings relate
     */
    protected string $className;

    /**
     * @var array Overridden table mapping information keyed on mapping key.
     * (eg. ['name' => 'MyOtherTable', 'repositoryClassName' => 'MyRepo'])
     */
    protected array $tableOverrides = [];

    /**
     * @var array Overridden columnn mapping information keyed on property name, then mapping key.
     * (eg. ['surname' => ['name' => 'alt_surname_column', 'isReadOnly' => true]])
     */
    protected array $columnOverrides =[];

    /**
     * @var array Overridden relationship mapping information keyed on property name, then mapping key.
     * (eg. ['child' => ['sourceJoinColumn' => 'alt_child_id', 'lazyLoad' => false]])
     */
    protected array $relationshipOverrides = [];

    /**
     * @var string Name of class to use for collections where one-to-many relationships require a custom collection
     * class rather than a simple array. All -to-many collections for the class will use the specified class.
     */
    protected string $collectionClass;
    
    /**
     * @var EntityFactoryInterface Factory to use for creating entities. If no factory is supplied, entities will be
     * created directly using the new keyword with no arguments passed to the constructor.
     */
    protected EntityFactoryInterface $entityFactory;

    /**
     * @var string Name of a JSON file to override entity mapping information.
     */
    protected string $mappingFile;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function __serialize(): array
    {
        $array = [];
        foreach (get_object_vars($this) as $property => $value) {
            //Entity factories might not be serializable, so just use the class name
            //(this is just to generate a hash, not to be unserialized)
            if ($property == 'entityFactory' && !empty($value)) {
                try {
                    $stringified = $this->serialize($value);
                    //All ok, so carry on
                    $array[$property] = $value;
                } catch (\Throwable $ex) {
                    $array[$property] = get_class($value);
                }
            } else  {
                $array[$property] = $value;
            }
        }

        return $array;
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key == 'entityFactory' && is_string($value)) {
                throw new \RuntimeException('Cannot deserialize config object with an unserializable entity factory.');
            }
            $this->setConfigOption($key, $value);
        }
    }
    
    public function setMappingFile(string $value): void
    {
        if (!is_file($value)) {
            throw new ObjectiphyException("Mapping file does not exist ($value)");
        }
        try {
            $contents = json_decode(file_get_contents($value), true);
            if (isset($contents['className']) && $contents['className'] == $this->className) {
                $tableOverrides = $contents['table'] ?? [];
                $columnOverrides = $contents['columns'] ?? [];
                $relationshipOverrides = $contents['relationships'] ?? [];
                $this->setConfigOption(self::TABLE_OVERRIDES, $tableOverrides);
                $this->setConfigOption(self::COLUMN_OVERRIDES, $columnOverrides);
                $this->setConfigOption(self::RELATIONSHIP_OVERRIDES, $relationshipOverrides);
            }
        } catch (\Throwable $ex) {}
    }
}
