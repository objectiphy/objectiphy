<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Query;

use Objectiphy\Objectiphy\Contract\PaginationInterface;

class Pagination implements PaginationInterface
{
    const DEFAULT_RECORDS_PER_PAGE = 50;

    /** @var int */
    private $recordsPerPage;
    /** @var int */
    private $pageNo;
    /** @var int */
    private $noOfPages;
    /** @var int */
    private $totalRecords;

    public function __construct($recordsPerPage = null, $pageNo = 1)
    {
        $this->recordsPerPage = intval($recordsPerPage) ?: self::DEFAULT_RECORDS_PER_PAGE;
        $this->pageNo = intval($pageNo) ?: 1;
    }

    /**
     * When paginating results, the first query just gets a count of records, and then calls this method with the total.
     * This can then be used to calculate how many pages of results there are.
     * @param int $totalRecords Total number of records available.
     */
    public function setTotalRecords($totalRecords)
    {
        $this->totalRecords = intval($totalRecords);
        $this->calculateNoOfPages();
        $this->pageNo = $this->getNoOfPages() >= $this->pageNo ? $this->pageNo : $this->getNoOfPages();
    }

    /**
     * Get current page count.
     * @return int
     */
    public function getNoOfPages()
    {
        return $this->noOfPages;
    }

    /**
     * @return int Current page number.
     */
    public function getPageNo()
    {
        return $this->pageNo;
    }

    /**
     * @return int How many records to return per page (query).
     */
    public function getRecordsPerPage()
    {
        return $this->recordsPerPage;
    }

    /**
     * @return int Total number of records.
     */
    public function getTotalRecords()
    {
        return $this->totalRecords;
    }

    /**
     * @return int The record offset for use in a database query
     */
    public function getOffset()
    {
        $offset = ($this->getPageNo() - 1) * $this->getRecordsPerPage();
        return $offset > 0 ? $offset : 0;
    }

    /**
     * Set current page count.
     */
    private function calculateNoOfPages()
    {
        $this->noOfPages = intval(ceil($this->totalRecords / intval($this->recordsPerPage ?: self::DEFAULT_RECORDS_PER_PAGE) ?: 1));
    }
}
