<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Contract;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 */
interface TransactionInterface
{
    /**
     * @return bool Whether or not the transaction was started - please do not throw an exception if a transaction is
     * already underway - we are assuming nested transactions will be handled gracefully.
     */
    public function beginTransaction(): bool;

    /**
     * @return bool Whether or not the transaction was rolled back.
     */
    public function rollback(): bool;

    /**
     * @return bool Whether or not the transaction was committed - please do not throw an exception if we are within
     * a nested transaction - just return true and let the outer transaction do the actual commit.
     */
    public function commit(): bool;
}
