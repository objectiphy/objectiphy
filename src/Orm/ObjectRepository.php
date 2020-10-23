<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Config\FindOptions;
use Objectiphy\Objectiphy\Contract\ExplanationInterface;
use Objectiphy\Objectiphy\Contract\ObjectReferenceInterface;
use Objectiphy\Objectiphy\Contract\ObjectRepositoryInterface;
use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Criteria\CB;
use Objectiphy\Objectiphy\Query\QB;

/**
 * Main entry point for all ORM operations
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
class ObjectRepository implements ObjectRepositoryInterface
{
    protected ConfigOptions $configOptions;
    protected ObjectMapper $objectMapper;
    protected ObjectFetcher $objectFetcher;
    protected ObjectPersister $objectPersister;
    protected ObjectRemover $objectRemover;
    protected PaginationInterface $pagination;
    protected array $orderBy;
    protected MappingCollection $mappingCollection;

    public function __construct(
        ObjectMapper $objectMapper,
        ObjectFetcher $objectFetcher,
        ObjectPersister $objectPersister,
        ObjectRemover $objectRemover,
        ConfigOptions $configOptions = null
    ) {
        $this->objectMapper = $objectMapper;
        $this->objectFetcher = $objectFetcher;
        $this->objectPersister = $objectPersister;
        $this->objectRemover = $objectRemover;
        if (!$configOptions) {
            $configOptions = new ConfigOptions();
        }
        $this->setConfiguration($configOptions);
    }

    /**
     * @param ConfigOptions $configOptions
     */
    public function setConfiguration(ConfigOptions $configOptions): void
    {
        $this->configOptions = $configOptions;
        $this->updateConfig();
    }

    /**
     * Set a general configuration option by name. Available options are defined on
     * the Objectiphy\Objectiphy\Config\ConfigOptions class.
     * @param string $optionName
     * @param $value
     */
    public function setConfigOption(string $optionName, $value)
    {
        $this->configOptions->setConfigOption($optionName, $value);
        $this->updateConfig();
    }

    /**
     * Set an entity-specific configuration option by name. Available options are 
     * defined on the Objectiphy\Objectiphy\Config\ConfigEntity class.
     * @param string $entityClassName
     * @param string $optionName
     * @param $value
     */
    public function setEntityConfigOption(string $entityClassName, string $optionName, $value)
    {
        $entityConfigs = $this->configOptions->getConfigOption('entityConfig');
        $entityConfig = $entityConfig[$entityClassName] ?? new ConfigEntity();
        $entityConfig->setConfigOption($optionName, $value);
        $entityConfigs[$entityClassName] = $entityConfig;
        $this->setConfigOption('entityConfig', $entityConfigs);
    }

    /**
     * Setter for the parent entity class name.
     * @param string $className
     */
    public function setClassName(string $className): void
    {
        $this->mappingCollection = $this->objectMapper->getMappingCollectionForClass($className);
        $findOptions = FindOptions::create($this->mappingCollection);
        $this->objectFetcher->setFindOptions($findOptions);
    }

    /**
     * Getter for the parent entity class name.
     * @return string
     */
    public function getClassName(): string
    {
        return $this->mappingCollection->getEntityClassName() ?? '';
    }

    /**
     * Set a pagination object (to store and supply information about how the results are paginated)
     * @param PaginationInterface
     */
    public function setPagination(PaginationInterface $pagination): void
    {
        $this->pagination = $pagination;
    }

    /**
     * @param array $orderBy Key = property name, value = ASC or DESC.
     */
    public function setOrderBy(array $orderBy): void
    {
        $this->orderBy = $orderBy;
    }

    /**
     * Find a single record (and hydrate it as an entity) with the given primary key value. Compatible with the
     * equivalent method in Doctrine.
     * @param mixed $id Primary key value - for composite keys, can be an array
     * @return object|null
     */
    public function find($id): ?object
    {
        $this->assertClassNameSet();
        $pkProperties = $this->mappingCollection->getPrimaryKeyProperties(true);
        if (!$pkProperties) {
            $errorMessage = sprintf('The current entity (`%1$s`) does not have a primary key, so you cannot use the find method. Either specify a primary key in the mapping information, or use findOneBy instead.', $this->getClassName());
            $this->throwException(new ObjectiphyException($errorMessage));
        }

        return $this->findOneBy(array_combine($pkProperties, is_array($id) ?: [$id]));
    }

    /**
     * Find a single record (and hydrate it as an entity) for the given criteria. Compatible with the equivalent method
     * in Doctrine.
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @return object|null
     */
    public function findOneBy(array $criteria = []): ?object
    {
        $findOptions = FindOptions::create($this->mappingCollection, ['multiple' => false, 'criteria' => $criteria]);
        $this->objectFetcher->setFindOptions($findOptions);

        return $this->doFindBy();
    }

    /**
     * Return the latest record from a group
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param string|null $commonProperty Property on root entity whose value you want to group by (see also the
     * setCommonProperty method).
     * @param string|null $recordAgeIndicator Fully qualified database column or expression that determines record age
     * (see also the setCommonProperty method).
     * @return object|null
     */
    public function findLatestOneBy(
        array $criteria = [],
        ?string $commonProperty = null,
        ?string $recordAgeIndicator = null
    ): ?object {

        //TODO: common property/age indicator

        $findOptions = FindOptions::create($this->mappingCollection, [
            'multiple' => false,
            'latest' => true,
            'criteria' => $criteria
        ]);
        $this->objectFetcher->setFindOptions($findOptions);

        return $this->doFindBy($criteria);
    }

    /**
     * Return the latest record from each group
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param string|null $commonProperty Property on root entity whose value you want to group by (see also the
     * setCommonProperty method).
     * @param string|null $recordAgeIndicator Fully qualified database column or expression that determines record age
     * (see also the setCommonProperty method).
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param boolean $multiple For internal use (when this method is called by the findLatestOneBy method).
     * @param boolean $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return iterable
     */
    public function findLatestBy(
        array $criteria,
        ?string $commonProperty = null,
        ?string $recordAgeIndicator = null,
        ?string $keyProperty = null,
        bool $multiple = true,
        bool $fetchOnDemand = false
    ): ?iterable {
        return null;
    }

    /**
     * Find all records that match the given criteria (and hydrate them as entities). Compatible with the equivalent
     * method in Doctrine.
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param array|null $orderBy Array of properties and optionally sort directions (eg. ['child.name'=>'DESC',
     * 'date']).
     * @param int|null $limit Only here to provide compatibility with Doctrine - normally we would use setPagination.
     * @param int|null $offset Only here to provide compatibility with Doctrine - normally we would use setPagination.
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param boolean $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set (for streaming large amounts of data).
     * @return array|object|null
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        $limit = null,
        $offset = null,
        ?string $keyProperty = null,
        bool $fetchOnDemand = false
    ): ?iterable {
        return null;
    }

    /**
     * Alias for findBy but automatically sets the $fetchOnDemand flag to true and avoids needing to supply null values
     * for the arguments that are not applicable (findBy thus remains compatible with Doctrine).
     * @param array $criteria
     * @param array|null $orderBy
     * @return array|null
     */
    public function findOnDemandBy(
        array $criteria,
        ?array $orderBy = null
    ): ?iterable {
        return null;
    }

    /**
     * Find all records. Compatible with the equivalent method in Doctrine.
     * @param string|null $keyProperty If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param boolean $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return array|null
     */
    public function findAll(?array $orderBy = null, ?string $keyProperty = null, bool $fetchOnDemand = false): ?iterable
    {
        return null;
    }

    /**
     * Insert or update the supplied entity.
     * @param object $entity The entity to insert or update.
     * @param bool $updateChildren Whether or not to also update any child objects.
     * @param bool $replace Allow for insert even if primary key has a value (typically for use where the primary key is
     * also a foreign key)
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntity(object $entity, bool $updateChildren = true, bool $replace = false): ?int
    {
        return null;
    }

    /**
     * Insert or update the supplied entities.
     * @param array $entities Array of entities to insert or update.
     * @param bool $updateChildren Whether or not to also insert any new child objects.
     * @param bool $replace Allow for insert even if primary key has a value (typically for use where the primary key is
     * also a foreign key)
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntities(array $entities, bool $updateChildren = true, bool $replace = false): ?int
    {
        return null;
    }

    /**
     * Create a proxy class for an object so that it does not have to be fully hydrated just to save it as a child of
     * another entity.
     * @param string $className Name of the class to proxy.
     * @param mixed $id Value of the primary key for the instance of the class this reference will represent
     * (can be an array if there is a composite primary key).
     * @param array $constructorParams
     * @return ObjectReferenceInterface|null The resulting object will extend the class name specified, as well as
     * implementing the ObjectReferenceInterface. Returns null if the class does not exist.
     */
    public function getObjectReference($className, $id, array $constructorParams = []): ?ObjectReferenceInterface
    {
        return null;
    }

    /**
     * @return Explanation Information about how the latest result was obtained.
     */
    public function getExplanation(): ?ExplanationInterface
    {
        return null;
    }

    protected function doFindBy()
    {
        $this->assertClassNameSet();
        return $this->objectFetcher->doFindBy();
    }

    protected function normalizeCriteria(array $criteria = [])
    {
        $pkProperty = '';
        if (is_int(array_key_first($criteria) && is_scalar(reset($criteria)))) { //Plain list of primary keys passed in
            $pkProperties = $this->mappingCollection->getPrimaryKeyProperties(true);
            if (!$pkProperties || count($pkProperties) !== 1) {
                $message = sprintf('The criteria passed in is a plain list of values, but entity \'%1$s\' has a composite key so there is insufficient information to identify which records to return.', $this->getClassName());
                throw new ObjectiphyException($message);
            }
            $pkProperty = $pkProperties[0];
        }
        $queryBuilder = QB::create();
        $normalizedCriteria = $queryBuilder->normalize($criteria, $pkProperty);
        
        return $normalizedCriteria;
    }

    /**
     * @throws ObjectiphyException
     */
    protected function assertClassNameSet()
    {
        if (!$this->getClassName()) {
            $callingMethod = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'Unknown method';
            $message = sprintf('Please call setEntityClassName before calling %1$s.', $callingMethod);
            throw new ObjectiphyException($message);
        }
    }

    /**
     * One or more config options have changed, so pass along the required values to the various dependencies
     * (it is not recommended to pass the whole config object around and let things help themselves)
     */
    protected function updateConfig()
    {
        $this->objectMapper->setConfigOptions(
            $this->configOptions->eagerLoadToOne,
            $this->configOptions->eagerLoadToMany,
            $this->configOptions->guessMappings,
            $this->configOptions->tableNamingStrategy,
            $this->configOptions->columnNamingStrategy
        );
        
        $this->objectFetcher->setConfigOptions($this->configOptions);
    }
}
