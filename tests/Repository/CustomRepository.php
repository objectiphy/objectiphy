<?php

namespace Objectiphy\Objectiphy\Tests\Repository;

use Objectiphy\Objectiphy\Orm\ObjectRepository;
use Objectiphy\Objectiphy\Tests\Entity\TestParentCustomRepo;

class CustomRepository extends ObjectRepository
{
    public function find($id)
    {
        $parent = parent::find($id);
        $parent->setName('Loaded with custom repo!');

        return $parent;
    }
}
