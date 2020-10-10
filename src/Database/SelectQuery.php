<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Database;

class SelectQuery
{
    public string $select;
    public string $from;
    public array $joins = [];
    public string $where;
    public string $groupBy;
    public string $having;
    public string $orderBy;
    public ?int $limit;
    public ?int $offset;

    public function __construct(
        string $select = '',
        string $from = '',
        array $joins = [],
        string $where = '',
        string $groupBy = '',
        string $having = '',
        string $orderBy = '',
        int $limit = null,
        int $offset = null
    ) {
        $this->select = $select;
        $this->from = $from;
        $this->joins = $joins;
        $this->where = $where;
        $this->groupBy = $groupBy;
        $this->having = $having;
        $this->orderBy = $orderBy;
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function __toString(): string
    {
        $sql = $this->joinPart($this->select, 'SELECT');
        $sql .= $this->joinPart($this->from, 'FROM');
        foreach ($this->joins as $join) {
            $sql .= $this->joinPart($join);
        }
        $sql .= $this->joinPart($this->where, 'WHERE');
        $sql .= $this->joinPart($this->groupBy, 'GROUP BY');
        $sql .= $this->joinpart($this->having, 'HAVING');
        $sql .= $this->joinPart($this->orderBy, 'ORDER BY');
        $sql .= $this->joinPart($this->limit, 'LIMIT');
        $sql .= $this->joinpart($this->offset, 'OFFSET');

        return trim($sql);
    }

    /**
     * Join a part onto the string, ensuring one space between each part and optionally
     * prefix with a keyword, but only if that keyword is not already present.
     * @param string $part
     * @param string $keyword
     * @return string
     */
    private function joinPart(string $part, string $keyword = ''): string
    {
        $keywordPresent = strlen($keyword) == 0 || stripos(trim($part), $keyword) !== 0;
        $sql = $keywordPresent ? '' : $keyword . ' ';
        $sql = strlen($part) > 1 ? $sql . $part : '';

        return $sql;
    }
}
