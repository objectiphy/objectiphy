<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\AssignmentExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface InsertQueryInterface extends QueryInterface
{
    /**
     * Which class to insert.
     * @param string $className
     */
    public function setInsert(string $className): void;

    /**
     * Name of class being inserted.
     * @return string
     */
    public function getInsert(): string;

    /**
     * Assignment expressions for values to insert.
     * @param AssignmentExpression ...$assignments
     * @return void
     */
    public function setAssignments(AssignmentExpression ...$assignments): void;

    /**
     * Get the assignment expressions for values to insert.
     * @return array
     */
    public function getAssignments(): array;

    /**
     * Ensure query is complete, filling in any missing bits as necessary
     * @param MappingCollection $mappingCollection
     * @param SqlStringReplacer $stringReplacer
     * @param string|null $className
     * @param array $assignments Keyed by property name (these will be the dirty properties passed in from the
     * entity tracker).
     */
    public function finalise(
        MappingCollection $mappingCollection,
        SqlStringReplacer $stringReplacer,
        ?string $className = null,
        array $assignments = []
    ): void;
}
