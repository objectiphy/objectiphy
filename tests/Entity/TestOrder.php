<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Objectiphy\Objectiphy\Mapping\Relationship;

/**
 * @ORM\Table(name="objectiphy_test.order")
 */
class TestOrder
{
    /**
     * @ORM\Id
     */
    public int $id;

    /**
     * @ORM\Column
     */
    public $productName;

    /**
     * @ORM\Column
     */
    public $description;

    /**
     * @ORM\Column
     */
    public $price;

    /**
     * @Relationship(relationshipType="one_to_one", childClassName="TestCustomer", sourceJoinColumn="customer_id")
     */
    public $customer;
}
