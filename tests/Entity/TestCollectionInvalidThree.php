<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

class TestCollectionInvalidTree extends \ArrayIterator
{
    public function __construct(TestParent $notAnArray)
    {
        //Just used to test that it cannot be used as a collection class
    }
}
