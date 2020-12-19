<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Marmalade\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\QueryException;

if (interface_exists('\Doctrine\Common\Persistence\ObjectRepository')) {
    interface ObjectRepositoryBaseInterface extends \Doctrine\Common\Persistence\ObjectRepository {}
} else {
    interface ObjectRepositoryBaseInterface {}
}

/**
 * Objectiphy repository interface, compatible with Doctrine Repository interface, with additional methods for
 * persistence and other features.
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface ObjectRepositoryInterface extends ObjectRepositoryBaseInterface
{
    /**
     * @param ConfigOptions $configOptions
     */
    public function setConfiguration(ConfigOptions $configOptions): void;

    /**
     * Set a general configuration option by name. Available options are defined on
     * the Objectiphy\Objectiphy\Config\ConfigOptions class.
     * @param string $optionName
     * @param $value
     * @return mixed The previously set value (or default value if not previously set).
     */
    public function setConfigOption(string $optionName, $value);
    
    /**
     * @return string Name of the parent entity class
     */
    public function setClassName(string $className): void;

    /**
     * @return string Name of the parent entity class
     */
    public function getClassName(): string;

    /**
     * Set a pagination object (to store and supply information about how the results are paginated)
     * @param PaginationInterface
     */
    public function setPagination(PaginationInterface $pagination): void;

    /**
     * @param array $orderBy Key = property name, value = ASC or DESC.
     */
    public function setOrderBy(array $orderBy): void;

    /**
     * Find a single record (and hydrate it as an entity) with the given primary key value. Compatible with the
     * equivalent method in Doctrine.
     * @param mixed $id Primary key value - for composite keys, can be an array
     * @return object|array|null
     */
    public function find($id);

    /**
     * Find a single record (and hydrate it as an entity) for the given criteria. Compatible with the equivalent method
     * in Doctrine.
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @return object|array|null
     */
    public function findOneBy(array $criteria = []);

    /**
     * Return the latest record from a group
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param string|null $commonProperty Property on root entity whose value you want to group by (see also the
     * setCommonProperty method).
     * @param string|null $recordAgeIndicator Fully qualified database column or expression that determines record age
     * (see also the setCommonProperty method).
     * @return object|array|null
     */
    public function findLatestOneBy(
        array $criteria = [], 
        ?string $commonProperty = null,
        ?string $recordAgeIndicator = null
    );

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
    ): ?iterable;

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
     * set(for streaming large amounts of data).
     * @return array|object|null
     */
    public function findBy(
        array $criteria, 
        ?array $orderBy = null, 
        $limit = null,
        $offset = null,
        ?string $keyProperty = null, 
        bool $fetchOnDemand = false
    ): ?iterable;

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
    ): ?iterable;

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
    public function findAll(?array $orderBy = null, ?string $keyProperty = null, bool $fetchOnDemand = false): ?iterable;

    /**
     * Insert or update the supplied entity.
     * @param object $entity The entity to insert or update.
     * @param bool $saveChildren Whether or not to also update any child objects.
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntity(
        object $entity,
        ?bool $saveChildren = null,
        int &$insertCount = 0,
        int &$updateCount = 0
    ): int;

    /**
     * Insert or update the supplied entities.
     * @param array $entities Array of entities to insert or update.
     * @param bool $saveChildren Whether or not to also insert any new child objects.
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntities(
        array $entities,
        ?bool $saveChildren = null,
        int &$insertCount = 0,
        int &$updateCount = 0
    ): int;

   /**
     * Hard delete an entity (and cascade to children, if applicable).
     * @param object $entity The entity to delete.
     * @param boolean $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @param boolean $exceptionIfDisabled Whether or not to barf if deletes are disabled (probably only useful for
     * integration or unit tests)
     * @return int Number of records affected
     * @throws \Exception
     */
    public function deleteEntity(object $entity, $disableCascade = false, $exceptionIfDisabled = true): int;
    
    /**
     * Hard delete multiple entities (and cascade to children, if applicable).
     * @param array|\Traversable $entities The entities to delete.
     * @param boolean $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @return int Number of records affected
     * @throws \Exception
     */
    public function deleteEntities(
        \Traversable $entities,
        bool $disableCascade = false,
        bool $exceptionIfDisabled = true
    ): int;

    /**
     * Execute a select, insert, update, or delete query directly
     * @param QueryInterface $query
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int|object|array|null Query results, or total number of rows affected.
     * @throws QueryException
     */
    public function executeQuery(QueryInterface $query, int &$insertCount = 0, int &$updateCount = 0): ?int;

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
    public function getObjectReference(
        string $className,
        array $pkValues,
        array $constructorParams = []
    ): ?ObjectReferenceInterface;

    /**
     * @return Explanation Information about how the latest result was obtained.
     */
    public function getExplanation(): ?ExplanationInterface;

    /**
     * Clear entities from memory and require them to be re-loaded afresh from the database.
     * @param string|null $className If supplied, only the cache for the given class will be cleared, otherwise all.
     */
    public function clearCache(?string $className = null): void;
}
