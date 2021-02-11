<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Like an SQL query, but with expressions relating to objects and properties.
 */
interface DeleteQueryInterface extends QueryInterface
{
    public function setDelete(string $className): void;

    public function getDelete(): string;
}
