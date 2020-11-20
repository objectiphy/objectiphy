<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\QueryInterface;

class UpdateQuery implements QueryInterface
{
    public function getPropertyPaths(): array
    {
        return [];
    }

    public function __toString(): string
    {
        return '';
    }
}
