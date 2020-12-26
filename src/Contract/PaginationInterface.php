<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Interface for a pagination class. You can use the simple implementation provided with Objectiphy, or create your own
 * pagination class and implement this interface.
 */
interface PaginationInterface
{
    /**
     * When paginating results, the first query just gets a count of records, and then calls this method with the total.
     * This can then be used to calculate how many pages of results there are.
     * @param int $totalRecords Total number of records available.
     */
    public function setTotalRecords(int $totalRecords): void;
    
    /**
     * Get current page count.
     * @return int
     */
    public function getNoOfPages(): int;
    
    /**
     * @return int Current page number.
     */
    public function getPageNo(): int;
    
    /**
     * @return int How many records to return per page (query).
     */
    public function getRecordsPerPage(): int;
    
    /**
     * @return int Total number of records.
     */
    public function getTotalRecords(): int;
    
    /**
     * @return int The record offset for use in a database query
     */
    public function getOffset(): int;
}
