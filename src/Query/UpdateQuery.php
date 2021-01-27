<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\UpdateQueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Query to update one or more values in the database.
 */
class UpdateQuery extends Query implements UpdateQueryInterface
{
    /**
     * @var AssignmentExpression[]
     */
    private array $assignments;

    public function setUpdate(string $className): void
    {
        $this->setClassName($className);
    }

    public function getUpdate(): string
    {
        return $this->getClassName();
    }

    public function setAssignments(AssignmentExpression ...$assignments): void
    {
        $this->assignments = $assignments;
    }

    public function getAssignments(): array
    {
        return $this->assignments;
    }

    /**
     * Ensure query is complete, filling in any missing bits as necessary
     * @param MappingCollection $mappingCollection
     * @param string|null $className
     * @param array $assignments Keyed by property name (these will be the dirty properties passed in from the
     * entity tracker).
     * @throws QueryException
     */
    public function finalise(
        MappingCollection $mappingCollection,
        ?string $className = null,
        array $assignments = []
    ): void {
        if (!$this->isFinalised) {
            if (!$this->getUpdate()) {
                $this->setUpdate($className);
            }
            if (!$this->getAssignments() && $assignments) {
                $assignmentExpressions = [];
                foreach ($assignments as $key => $value) {
                    $propertyMapping = $mappingCollection->getPropertyMapping($key);
                    if ($propertyMapping->isReadOnly()) {
                        continue;
                    }
                    if (!($value instanceof AssignmentExpression)) {
                        $assignmentExpressions[] = new AssignmentExpression($key, $value);
                        //If scalar join, ensure we have the join
                        if ($propertyMapping->relationship->isScalarJoin()) {
                            $this->populateRelationshipJoin($mappingCollection, $propertyMapping);
                        }
                    }
                }
                $this->setAssignments(...$assignmentExpressions);
            }
        }
    }

    public function __toString(): string
    {
        if (!$this->assignments || !$this->getUpdate()) {
            throw new QueryException('Please finalise the query before use (ie. call the finalise method).');
        }

        $queryString = 'UPDATE ' . $this->getUpdate();
        $queryString .= ' ' . implode(' ', $this->getJoins());
        $queryString .= 'SET ' . implode(', ', $this->assignments);
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
}
