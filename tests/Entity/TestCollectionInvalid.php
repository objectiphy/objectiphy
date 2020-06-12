<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

class TestCollectionInvalid extends \ArrayIterator
{
    public function __construct(array $array, $otherRequiredParameter)
    {
        //Just used to test that it cannot be used as a collection class
    }
}
