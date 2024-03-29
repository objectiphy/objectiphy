<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

use Objectiphy\Objectiphy\Database\SqlStringReplacer;
use Objectiphy\Objectiphy\Mapping\MappingCollection;
use Objectiphy\Objectiphy\Query\AssignmentExpression;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Like an SQL query, but with expressions relating to objects and properties.
 */
interface UpdateQueryInterface extends QueryInterface
{
    /**
     * Set name of class to update.
     * @param string $className
     */
    public function setUpdate(string $className): void;

    /**
     * Get name of class being updated.
     * @return string
     */
    public function getUpdate(): string;

    /**
     * Assignment expressions for values to update.
     * @param AssignmentExpression ...$assignments
     * @return void
     */
    public function setAssignments(AssignmentExpression ...$assignments): void;

    /**
     * Get the assignment expressions for values to update.
     * @return AssignmentExpression[]
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
