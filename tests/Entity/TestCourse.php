<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Objectiphy\Objectiphy\Mapping\Relationship;

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
     * (annotations can be disabled by removing the @ symbol for test purposes - some missing items can be guessed)
     * ORM\ManyToMany(targetEntity="TestStudent", inversedBy="course")
     * ORM\JoinTable(name="student_course",
     *      joinColumns={@ORM\JoinColumn(name="course_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="student_id", referencedColumnName="id")}
     * )
     *
     * @Relationship(
     *     relationshipType="many_to_many",
     *     childClassName="TestStudent",
     *     bridgeJoinTable="student_course"
     * )
     */
    public array $students = [];
}
