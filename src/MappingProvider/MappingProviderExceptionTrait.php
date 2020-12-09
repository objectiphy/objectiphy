<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\MappingProvider;

use Objectiphy\Objectiphy\Exception\MappingException;

trait MappingProviderExceptionTrait
{
    protected $throwExceptions = false;
    protected $lastErrorMessage = '';

    public function setThrowExceptions(bool $value): void
    {
        $this->throwExceptions = $value;
        if (isset($this->mappingProvider)) {
            $this->mappingProvider->setThrowExceptions($value);
        }
    }

    public function getLastErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }

    private function handleException(\Throwable $ex): void
    {
        if ($this->throwExceptions) {
            if ($ex instanceof MappingException) {
                throw $ex;
            } else {
                throw new MappingException($ex->getMessage(), 0, $ex);
            }
        } else {
            $this->lastErrorMessage = $ex->getMessage();
        }
    }
}
