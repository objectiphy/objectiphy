<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Orm;

use Objectiphy\Objectiphy\Contract\PaginationInterface;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Query\QueryBuilderInterface;

/**
 * @package Objectiphy\Objectiphy
 * @author Russell Walker <rwalker.php@gmail.com>
 */
final class ObjectFetcher
{
    private QueryBuilderInterface $queryBuilder;
    private ObjectBinder $objectBinder;
    private array $options;
    
    public function __construct(QueryBuilderInterface $queryBuilder, ObjectBinder $objectBinder)
    {
        $this->queryBuilder = $queryBuilder;
        $this->objectBinder = $objectBinder;
        $this->setFindOptions();
    }

    /**
     * @param array $options Set specified options (anything unspecified will revert to default)
     */
    public function setFindOptions(array $options = [])
    {
        $this->options = [
            'multiple' => true,
            'latest' => false,
            'onDemand' => false,
            'keyProperty' => '',
        ];
        foreach ($this->options as $key => $value) {
            $this->options[$key] = $options[$key] ?? $this->options[$key];
        }
    }
        
    public function doFindBy(
        string $className,
        array $criteria = [],
        PaginationInterface $pagination
    ) {
        if (!empty($criteria) && !(reset($criteria) instanceof CriteriaExpression)) {
            throw new CriteriaException('Invalid criteria passed to ObjectFetcher::doFindBy. If you are overriding a findBy method of a repository, please call $criteria = $this->normalizeCriteria($criteria) on your repository first.');
        }

        $storageQueryBuilder = $this->repository->getStorageQueryBuilder();
        if ($multiple && $this->repository->getPagination()) {
            //Get count first
            $countSql = $storageQueryBuilder->getSelectQuery($criteria, $multiple, $latest, true);
            $recordCount = intval($this->fetchValue($countSql, $storageQueryBuilder->getQueryParams()));
            $this->repository->getPagination()->setTotalRecords($recordCount);
        }

        $storageQueryBuilder->setPagination($this->repository->getPagination());
        $storageQueryBuilder->setOrderBy($this->orderBy);
        $this->objectBinder->objectMapper->setScalarProperty($scalarProperty);
        $sql = $storageQueryBuilder->getSelectQuery($criteria, $multiple, $latest);

        if ($multiple && $iterable && $scalarProperty) {
            $result = $this->fetchIterableValues($sql, $this->storageQueryBuilder->getQueryParams());
        } elseif ($multiple && $iterable) {
            $result = $this->fetchIterableResult($sql, $this->storageQueryBuilder->getQueryParams(), $bindToEntities);
        } elseif ($multiple && $scalarProperty) {
            $result = $this->fetchValues($sql, $this->storageQueryBuilder->getQueryParams());
        } elseif ($multiple) {
            $result = $this->fetchResults($sql, $this->storageQueryBuilder->getQueryParams(), $keyProperty, $bindToEntities);
        } elseif ($scalarProperty) {
            $result = $this->fetchValue($sql, $this->storageQueryBuilder->getQueryParams());
        } else {
            $result = $this->fetchResult($sql, $this->storageQueryBuilder->getQueryParams(), $bindToEntities);
        }

        return $result;
    }
}
