<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\QueryInterface;
use Objectiphy\Objectiphy\Exception\QueryException;
use Objectiphy\Objectiphy\Mapping\MappingCollection;

class UpdateQuery extends Query implements QueryInterface
{
    private array $values;

    public function setUpdate(string $className): void
    {
        $this->setClassName($className);
    }

    public function getUpdate(): string
    {
        return $this->getClassName();
    }

    public function setAssignments(array $fields, array $values)
    {
        if (count($fields) != count($values)) {
            throw new QueryException('Field count and value count must match when setting assignments.');
        }
        $this->setFields($fields);
        $this->values = $values;
    }

    /**
     * @param array $fields If you want the original field expression objects, pass in an empty array and you shall
     * have them. Otherwise, you just get their string representations as keys to the return value array.
     * @return array
     */
    public function getAssignments(array &$fields = []): array
    {
        $fields = $this->getFields();

        return array_combine(array_map('strval', $fields), $this->values);
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
            if (!$this->getAssignments() && $assignments) {
                $this->setAssignments(array_keys($assignments), array_values($assignments));
            }
            parent::finalise($mappingCollection, $className);
        }
    }
}
