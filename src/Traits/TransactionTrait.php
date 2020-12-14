<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Traits;

trait TransactionTrait
{
    /**
     * Manually begin a transaction (if supported by the storage engine)
     */
    public function beginTransaction(): bool
    {
        return $this->storage->beginTransaction();
    }

    /**
     * Commit a transaction that was started manually (if supported by the storage engine)
     */
    public function commit(): bool
    {
        return $this->storage->commit();
    }

    /**
     * Rollback a transaction that was started manually (if supported by the storage engine)
     */
    public function rollback(): bool
    {
        return $this->storage->rollback();
    }
}
