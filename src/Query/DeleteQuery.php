<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Contract\DeleteQueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * Query to update one or more values in the database.
 */
class DeleteQuery extends Query implements DeleteQueryInterface
{
    public function setDelete(string $className): void
    {
        $this->setClassName($className);
    }

    public function getDelete(): string
    {
        return $this->getClassName();
    }
    
    public function __toString(): string
    {
        if (!$this->getDelete()) {
            throw new QueryException('Please finalise the query before use (ie. call the finalise method).');
        }
        $queryString = 'DELETE ' . $this->getDelete();
        $queryString .= ' ' . implode(' ', $this->getJoins());
        $queryString .= ' WHERE 1 ';
        if ($this->where) {
            foreach ($this->getWhere() as $criteriaExpression) {
                $queryString .= ' AND ' . (string) $criteriaExpression;
            }
        }
        
        return $queryString;
    }

    public function getPropertyPaths(): array
    {
        $paths = parent::getPropertyPaths();
        foreach ($this->assignments ?? [] as $assignment) {
            $paths = array_merge($paths, $assignment->getPropertyPaths());
        }

        return array_unique($paths);
    }

    /**
     * Ensure query is complete, filling in any missing bits as necessary (don't add joins for delete queries - if they
     * are needed, the user should supply them).
     * @param MappingCollection $mappingCollection
     * @param string|null $className
     */
    public function finalise(MappingCollection $mappingCollection, ?string $className = null)
    {
        if (!$this->isFinalised) {
            $this->mappingCollection = $mappingCollection;
            $className = $this->getDelete() ?: ($className ?? $mappingCollection->getEntityClassName());
            $this->setClassName($className);
            $this->isFinalised = true;
        }
    }
}
