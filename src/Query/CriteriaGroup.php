<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Exception\QueryException;

class CriteriaGroup implements CriteriaPartInterface
{
    public const GROUP_TYPE_START_AND = 'START_AND';
    public const GROUP_TYPE_START_OR = 'START_OR';
    public const GROUP_TYPE_END = 'END';

    public string $type;

    public function __construct(string $type)
    {
        if (defined('self::GROUP_TYPE_' . $type)) {
            $this->type = $type;
        } else {
            throw new QueryException('Criteria group type not recognised: ' . $type);
        }
    }
}
