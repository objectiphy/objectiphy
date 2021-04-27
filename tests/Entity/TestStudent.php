<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="objectiphy_test.student")
 */
class TestStudent
{
    /**
     * @ORM\Id
     */
    private $id;

    /**
     * @ORM\Column
     */
    private $firstName;

    /**
     * @ORM\Column
     */
    private $lastName;

    /**
     * @ORM\Column(name="intelligence_quotient")
     */
    private $iq;

    /**
     * @ManyToMany(targetEntity="TestCourse", mappedBy="students")
     */
    private array $courses;
}
