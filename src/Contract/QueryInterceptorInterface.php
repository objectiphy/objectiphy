<?php

namespace Objectiphy\Objectiphy\Contract;

/**
 * Inject an object that implements this interface into your repository if you need to
 * intercept and amend the SQL immediately before it is executed. Use of this feature
 * is discouraged, and was only added for backward compatibility reasons!
 */
interface QueryInterceptorInterface
{
    /**
     * You can amend the variables that are passed here by reference to change what gets executed.
     * Don't use this unless you absolutely have no other choice!
     */
    public function intercept(&$query, &$params): void;
    
    public function setCriteria($criteria): void;
    
    public function setParams(array $params): void;
}
