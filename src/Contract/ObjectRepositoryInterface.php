<?php

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\ConfigOptions;

if (interface_exists('Doctrine\Common\Persistence\ObjectRepository')) {
    interface ObjectRepositoryBaseInterface extends Doctrine\Common\Persistence\ObjectRepository {}
} else {
    interface ObjectRepositoryBaseInterface {}
}

/**
 * Interface ObjectRepositoryInterface
 * @package Marmalade\Objectiphy
 * @author Russell Walker <russell.walker@marmalade.co.uk>
 * Objectiphy repository interface, compatible with Doctrine Repository interface, with additional methods for
 * persistence and other features.
 */
interface ObjectRepositoryInterface extends ObjectRepositoryBaseInterface
{
    /**
     * @param ConfigOptions $configOptions
     */
    public function setConfiguration(ConfigOptions $configOptions): void;

    /**
     * @return string Name of the parent entity class
     */
    public function setEntityClassName(string $className): void;

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
     * @return object|null
     */
    public function find($id): ?object;

    /**
     * Find a single record (and hydrate it as an entity) for the given criteria. Compatible with the equivalent method
     * in Doctrine.
     * @param array $criteria An array of CriteriaExpression objects or key/value pairs, or criteria arrays. Compatible
     * with Doctrine criteria arrays, but also supports more options (see documentation).
     * @return object|null
     */
    public function findOneBy(array $criteria = []): ?object;

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
    ): ?object;

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
    ): iterable;

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
        ?int $limit = null, 
        ?int $offset = null, 
        ?string $keyProperty = null, 
        bool $fetchOnDemand = false
    ): iterable;

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
    ): iterable;

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
    public function findAll(?array $orderBy = null, ?string $keyProperty = null, bool $fetchOnDemand = false): iterable;

    /**
     * Insert or update the supplied entity.
     * @param object $entity The entity to insert or update.
     * @param bool $updateChildren Whether or not to also update any child objects.
     * @param bool $replace Allow for insert even if primary key has a value (typically for use where the primary key is
     * also a foreign key)
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntity(object $entity, bool $updateChildren = true, bool $replace = false): ?int;

    /**
     * Insert or update the supplied entities.
     * @param array $entities Array of entities to insert or update.
     * @param bool $updateChildren Whether or not to also insert any new child objects.
     * @param bool $replace Allow for insert even if primary key has a value (typically for use where the primary key is
     * also a foreign key)
     * @return int Number of rows affected.
     * @throws \Exception
     */
    public function saveEntities(array $entities, bool $updateChildren = true, bool $replace = false): ?int;

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
    public function getObjectReference($className, $id, array $constructorParams = []): ?ObjectReferenceInterface;

    /**
     * @return Explanation Information about how the latest result was obtained.
     */
    public function getExplanation(): ExplanationInterface;
}
