<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Orm\IterableResult;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Objectiphy repository interface, compatible with Doctrine Repository interface, with additional methods for
 * persistence and other features. Does not extend the Doctrine interface though, as that is too restrictive and
 * requires us to keep in line with any changes Doctrine might make in future.
 */
interface ObjectRepositoryInterface
{
    /**
     * Apply an entire config option set in one go. Typically you would set config options one at a time using
     * setConfigOption rather than this method.
     * @param ConfigOptions $configOptions
     */
    public function setConfiguration(ConfigOptions $configOptions): void;

    /**
     * Return all the current configuration options
     * @return ConfigOptions
     */
    public function getConfiguration(): ConfigOptions;

    /**
     * Reset config options to their default values.
     * @param string $configFile Optionally specify a config file to load the defaults from.
     */
    public function resetConfiguration(string $configFile = ''): void;

    /**
     * Set a general configuration option by name. Available options are defined on
     * the Objectiphy\Objectiphy\Config\ConfigOptions class.
     * @param string $optionName
     * @param $value
     * @return mixed The previously set value (or default value if not previously set).
     */
    public function setConfigOption(string $optionName, $value);
    
    /**
     * Set an entity-specific configuration option by name. Available options are
     * defined on the Objectiphy\Objectiphy\Config\ConfigEntity class. The previous
     * value of the option is returned.
     * @param string $entityClassName
     * @param string $optionName
     * @param $value
     * @return mixed The previously set value (or default value if not previously set).
     */
    public function setEntityConfigOption(string $entityClassName, string $optionName, $value);

    /**
     * @param string $className Name of the parent entity class.
     */
    public function setClassName(string $className): void;

    /**
     * Compatible with the equivalent method in Doctrine (therefore, no return type).
     * @return string Name of the parent entity class.
     */
    public function getClassName(): string;

    /**
     * Set a pagination object (to store and supply information about how the results are paginated).
     * @param PaginationInterface
     */
    public function setPagination(PaginationInterface $pagination): void;

    /**
     * Return the current pagination object.
     * @return PaginationInterface|null
     */
    public function getPagination(): ?PaginationInterface;

    /**
     * @param array $orderBy Key = property name, value = ASC or DESC.
     * No return type for backward compatibility
     */
    public function setOrderBy(array $orderBy);

    /**
     * Find a single record (and hydrate it as an entity) with the given primary key value. Compatible with the
     * equivalent method in Doctrine.
     * @param mixed $id Primary key value - for composite keys, can be an array.
     * @return mixed
     */
    public function find($id);

    /**
     * Find a single record (and hydrate it as an entity) for the given criteria. Compatible with the equivalent method
     * in Doctrine.
     * @param array|SelectQueryInterface $criteria A query, an array of CriteriaExpression objects or key/value pairs, 
     * or criteria arrays. Compatible with Doctrine criteria arrays, but also supports more options (see documentation).
     * @return mixed
     */
    public function findOneBy($criteria = []);

    /**
     * Find all records that match the given criteria (and hydrate them as entities). Compatible with the equivalent
     * method in Doctrine.
     * @param array|SelectQueryInterface $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @param array|null $orderBy Array of properties and optionally sort directions (eg. ['child.name'=>'DESC',
     * 'date']).
     * @param int|null $limit Only here to provide compatibility with Doctrine - normally we would use setPagination.
     * @param int|null $offset Only here to provide compatibility with Doctrine - normally we would use setPagination.
     * @param string|null $indexBy If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param bool $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return mixed
     */
    public function findBy(
        $criteria,
        ?array $orderBy = null, 
        $limit = null,
        $offset = null,
        ?string $indexBy = null,
        bool $fetchOnDemand = false
    );

    /**
     * Alias for findBy but automatically sets the $fetchOnDemand flag to true and avoids needing to supply null values 
     * for the arguments that are not applicable (findBy thus remains compatible with Doctrine).
     * @param array|SelectQueryInterface $criteria
     * @param array|null $orderBy
     * @return iterable|null
     */
    public function findOnDemandBy(
        $criteria,
        ?array $orderBy = null
    ): ?iterable;

    /**
     * Find the value of a single property of a single entity
     * @param array $criteria
     * @param string $valueProperty
     * @return mixed
     */
    public function findOneValueBy($criteria = [], string $valueProperty = '');

    /**
     * Find the value of a single property on multiple entities
     * @param array $criteria
     * @param string $valueProperty
     * @param array|null $orderBy
     * @param string|null $indexBy
     * @param bool $fetchOnDemand
     * @return mixed
     */
    public function findValuesBy(
        $criteria = [],
        string $valueProperty = '',
        ?array $orderBy = null,
        ?string $indexBy = null,
        bool $fetchOnDemand = false
    );

    /**
     * Find the value of a single property on multiple entities and return as an on-demand iterable result
     * @param array $criteria
     * @param string $valueProperty
     * @param array|null $orderBy
     * @return IterableResult
     */
    public function findOnDemandValuesBy($criteria = [], string $valueProperty = '', ?array $orderBy = null): IterableResult;
    
    /**
     * Find all records. Compatible with the equivalent method in Doctrine.
     * @param array|null $orderBy
     * @param string|null $indexBy If you want the resulting array to be associative, based on a value in the
     * result, specify which property to use as the key here (note, you can use dot notation to key by a value on a
     * child object, but make sure the property you use has a unique value in the result set, otherwise some records
     * will be lost).
     * @param bool $fetchOnDemand Whether or not to read directly from the database on each iteration of the result
     * set(for streaming large amounts of data).
     * @return mixed
     */
    public function findAll(?array $orderBy = null, ?string $indexBy = null, bool $fetchOnDemand = false);

    /**
     * Count records that match criteria
     * @param array $criteria
     * @return int
     */
    public function count($criteria = []): int;

    /**
     * Insert or update the supplied entity.
     * @param object $entity The entity to insert or update.
     * @param bool $saveChildren Whether or not to also update any child objects.
     * @param bool $replace Whether or not to attempt to insert, and if the record already exists, update it (for
     * cases where you are generating a primary key value yourself)
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntity(
        object $entity,
        ?bool $saveChildren = null,
        ?bool $replace = false,
        int &$insertCount = 0,
        int &$updateCount = 0
    ): int;

    /**
     * Insert or update the supplied entities.
     * @param array $entities Array of entities to insert or update.
     * @param bool $saveChildren Whether or not to also insert any new child objects.
     * @param bool $replace Whether or not to attempt to insert, and if the record already exists, update it (for
     * cases where you are generating a primary key value yourself)
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntities(
        array $entities,
        ?bool $saveChildren = null,
        ?bool $replace = false,
        int &$insertCount = 0,
        int &$updateCount = 0
    ): int;

    /**
     * If the table relating to this repository's class has an autoincremented primary key, and a record has been
     * inserted, return the new ID.
     * @return int|null
     */
    public function getLastInsertId(): ?int;

   /**
     * Hard delete an entity (and cascade to children, if applicable).
     * @param object $entity The entity to delete.
     * @param bool $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @param bool $exceptionIfDisabled Whether or not to throw an exception if deletes have been disabled. If false,
     * it will just silently return zero records affected (probably only useful for integration or unit tests).
     * @return int Number of records affected
     * @throws \Exception
     */
    public function deleteEntity(object $entity, $disableCascade = false, $exceptionIfDisabled = true): int;

    /**
     * Hard delete multiple entities (and cascade to children, if applicable).
     * @param iterable $entities The entities to delete.
     * @param bool $disableCascade Whether or not to suppress cascading deletes (deletes will only normally be
     * cascaded if the mapping definition explicitly requires it, but you can use this flag to override that).
     * @param bool $exceptionIfDisabled Whether or not to throw an exception if deletes have been disabled. If false,
     * it will just silently return zero records affected (probably only useful for integration or unit tests).
     * @return int Number of records affected.
     * @throws \Exception
     */
    public function deleteEntities(
        iterable $entities,
        bool $disableCascade = false,
        bool $exceptionIfDisabled = true
    ): int;

    /**
     * Execute a select, insert, update, or delete query directly.
     * @param QueryInterface $query
     * @param int $insertCount Number of rows inserted.
     * @param int $updateCount Number of rows updated.
     * @param int $deleteCount Number of rows deleted.
     * @return int|object|iterable|null Query results, or total number of rows affected.
     * @throws QueryException
     */
    public function executeQuery(QueryInterface $query, int &$insertCount = 0, int &$updateCount = 0, int &$deleteCount = 0);

    /**
     * Create a proxy class for an object so that it does not have to be fully hydrated just to save it as a child of
     * another entity.
     * @param string $className Name of the class to proxy.
     * @param array $pkValues Value of the primary key for the instance of the class this reference will represent,
     * keyed by property name.
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
     * @return ExplanationInterface Information about how the latest result was obtained.
     */
    public function getExplanation(): ?ExplanationInterface;

    /**
     * Clear entities from memory and require them to be re-loaded afresh from the database.
     * @param string|null $className If supplied, only the cache for the given class will be cleared, otherwise all.
     */
    public function clearCache(?string $className = null): void;

    /**
     * Delete all collected data (to free up memory for large and/or multiple queries).
     */
    public function clearQueryHistory(): void;

    /**
     * Set all primary keys to null so you can save a copy as a new record with
     * all of its children (note, this will not set keys to null if there is an
     * autoIncrement mapping definition set to false). This method does NOT
     * clone the object, it UPDATES it to remove (set to null, or unset) all
     * primary key property values (ie. on the parent and all child objects).
     * You can then save it back to the database to create a copy with new
     * autoincrement IDs.
     */
    public function clearAllPrimaryKeyValues(object $entity, array $excludeProperties = []): void;

    /**
     * Convenience method to allow you to perform some action on each object in the hierarchy.
     * If the closure returns false, the traversal will stop (allows you to stop going deeper
     * than is necessary, although infinite recursion will be prevented automatically anyway, 
     * and you can also specify a maximum depth)
     */
    public function traverseHierarchy(object $entity, \closure $closure, array $excludeProperties = [], int $maxDepth = 5): void;
}
