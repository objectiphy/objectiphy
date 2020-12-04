<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

class InsertQuery extends Query implements QueryInterface
{
    /**
     * @var AssignmentExpression[]
     */
    private array $assignments;

    public function setInsertInto(string $className): void
    {
        $this->setClassName($className);
    }

    public function getInsertInto(): string
    {
        return $this->getClassName();
    }

    public function setAssignments(AssignmentExpression ...$assignments)
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
     */
    public function finalise(
        MappingCollection $mappingCollection,
        ?string $className = null,
        array $assignments = []
    ): void {
        if (!$this->isFinalised) {
            if (!$this->getInsertInto()) {
                $this->setInsertInto($className);
            }
            if (!$this->getAssignments() && $assignments) {
                $assignmentExpressions = [];
                foreach ($assignments as $key => $value) {
                    if (!($value instanceof AssignmentExpression)) {
                        $assignmentExpressions[] = new AssignmentExpression($key, $value);
                    }
                }
                $this->setAssignments(...$assignmentExpressions);
            }
            //Don't call parent finalise as we don't need joins by default
        }
    }

    public function __toString(): string
    {
        if (!$this->assignments || !$this->getInsertInto()) {
            throw new QueryException('Please finalise the query before use (ie. call the finalise method).');
        }
        $useParams = $params !== null;
        $queryString = 'INSERT INTO ' . $this->getInsertInto();
        $queryString .= ' ' . implode(' ', $this->getJoins());
        $queryString .= 'SET ' . implode(', ', $this->assignments);

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
