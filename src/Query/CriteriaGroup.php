<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\CriteriaPartInterface;
use Objectiphy\Objectiphy\Contract\JoinPartInterface;
use Objectiphy\Objectiphy\Exception\QueryException;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Represents an opening or closing parenthesis in a group of criteria, starting with either AND or OR.
 * ie. "AND (", "OR (", or ")".
 */
class CriteriaGroup implements CriteriaPartInterface, JoinPartInterface
{
    public const GROUP_TYPE_START_AND = 'START_AND';
    public const GROUP_TYPE_START_OR = 'START_OR';
    public const GROUP_TYPE_END = 'END';

    public string $type;

    /**
     * CriteriaGroup constructor.
     * @param string $type
     * @throws QueryException
     */
    public function __construct(string $type)
    {
        if (defined('self::GROUP_TYPE_' . $type)) {
            $this->type = $type;
        } else {
            throw new QueryException('Criteria group type not recognised: ' . $type);
        }
    }

    public function __toString(): string
    {
        switch ($this->type) {
            case self::GROUP_TYPE_START_AND:
                return 'AND (';
            case self::GROUP_TYPE_START_OR:
                return 'OR (';
            default:
                return ')';
        }
    }
}
