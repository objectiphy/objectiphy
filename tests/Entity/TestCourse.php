<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="objectiphy_test.course")
 */
class TestCourse
{
    /**
     * @ORM\Id
     */
    public $id;

    /**
     * @ORM\Column
     */
    public $name;

    /**
     * @ORM\Column
     */
    public $description;

    /**
     * @ORM\Column
     */
    public $cost;

    /**
     * Join columns are optional - they default to the values shown here
     * (there is no @ symbol for ORM/JoinTable to disable it for test purposes - with or without should work the same way)
     * @ORM\ManyToMany(targetEntity="TestStudent", inversedBy="course")
     * ORM\JoinTable(name="student_course",
     *      joinColumns={@ORM\JoinColumn(name="course_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="student_id", referencedColumnName="id")}
     * )
     */
    public array $students = [];
}
