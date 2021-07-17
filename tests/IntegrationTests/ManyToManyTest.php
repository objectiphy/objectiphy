<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Tests\Entity\TestCourse;
use Objectiphy\Objectiphy\Tests\Entity\TestCourseOrphan;
use Objectiphy\Objectiphy\Tests\Entity\TestStudent;
use Objectiphy\Objectiphy\Tests\Entity\TestStudentOrphan;

class ManyToManyTest extends IntegrationTestBase
{
    public function testManyToManyDefault()
    {
        $this->testName = 'Many-to-many Reading default' . $this->getCacheSuffix();
        $this->doTests();
    }

    public function testManyToManyMixed()
    {
        $this->testName = 'Many-to-many Reading mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    public function testManyToManyLazy()
    {
        $this->testName = 'Many-to-many Reading lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    public function testManyToManyEager()
    {
        $this->testName = 'Many-to-many Reading eager' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    //Repeat with the cache turned off
    public function testManyToManyDefaultNoCache()
    {
        $this->disableCache();
        $this->testManyToManyDefault();
    }

    public function testManyToManyMixedNoCache()
    {
        $this->disableCache();
        $this->testManyToManyMixed();
    }

    public function testManyToManyLazyNoCache()
    {
        $this->disableCache();
        $this->testManyToManyLazy();
    }

    public function testManyToManyEagerNoCache()
    {
        $this->disableCache();
        $this->testManyToManyEager();
    }

    protected function doTests()
    {
        $this->doReadingTests();
        $this->doWritingTests();
        $this->doDeletingTests();
        $this->doOrphanRemovalTest();
        $this->doOrphanRemovalOnDeleteTest();
        $this->doSuppressedOrphanRemovalTest();
    }

    protected function doReadingTests()
    {
        $this->objectRepository->setClassName(TestCourse::class);
        $course = $this->objectRepository->find(1);
        $students = $course->students;
        $this->assertEquals(4, count($students));

        $firstStudentCourses = $students[0]->courses;
        $this->assertEquals(2, count($firstStudentCourses));
    }

    protected function doWritingTests()
    {
        //Add new element to a many-to-many collection
        $this->objectRepository->setClassName(TestCourse::class);
        $course = $this->objectRepository->find(1);
        $this->assertEquals(4, count($course->students));
        $student = new TestStudent();
        $student->firstName = 'Lemony';
        $student->lastName = 'Snicket';
        $student->iq = 161;
        $student->courses[] = $course;
        $course->students[] = $student;

        $insertCount = 0;
        $updateCount = 0;
        $deleteCount = 0;
        $this->objectRepository->saveEntity($course, null, null, $insertCount, $updateCount, $deleteCount);
        $this->assertEquals(2, $insertCount);
        $this->assertEquals(0, $updateCount);
        $this->assertEquals(0, $deleteCount);

        $course2 = $this->objectRepository->find(1);
        $this->assertEquals(5, count($course2->students));

        $this->objectRepository->setClassName(TestStudent::class);
        $student = $this->objectRepository->findOneBy(['firstName' => 'Lemony', 'lastName' => 'Snicket']);
        $this->assertEquals(161, $student->iq);
        $this->assertEquals(1, count($student->courses));
        $this->assertEquals(1, $student->courses[0]->id);

        //Update an element in a many-to-many collection
        $this->assertEquals('8500.00', $student->courses[0]->cost);
        $student->courses[0]->cost = '7500.00';
        $studentId = $student->id;
        $this->objectRepository->saveEntity($student, null, false, $insertCount, $updateCount);
        $this->assertEquals(0, $insertCount);
        $this->assertEquals(1, $updateCount);

        $refreshedStudent = $this->objectRepository->find($studentId);
        $this->assertEquals('7500.00', $refreshedStudent->courses[0]->cost);
    }

    protected function doDeletingTests()
    {
        $this->setUp(); //Forget about anything added by previous tests

        $this->objectRepository->setClassName(TestCourse::class);
        $course = $this->objectRepository->find(1);
        $this->assertEquals(4, count($course->students));

        unset($course->students[1]);
        unset($course->students[2]);

        $insertCount = 0;
        $updateCount = 0;
        $deleteCount = 0;
        $this->objectRepository->saveEntity($course, null, false, $insertCount, $updateCount, $deleteCount);
        $this->assertEquals(0, $insertCount);
        $this->assertEquals(0, $updateCount);
        $this->assertEquals(2, $deleteCount);

        $refreshedCourse = $this->objectRepository->find(1);
        $this->assertEquals(2, count($course->students));
    }

    public function doOrphanRemovalTest()
    {
        // Check that no other parent has this child before orphan removal (we will not check parents of a
        // different class as we have no way of knowing which classes might be parents. If you have switched on
        // orphan removal, we will assume you have a private relationship, so no other class types will be involved).
        $this->objectRepository->setClassName(TestCourseOrphan::class);
        $afflictedCourse = $this->objectRepository->find(4);
        //Remove a child entity which has orphan removal but where the child has another parent - it should not be deleted
        $students =& $afflictedCourse->students;
        $unrulyStudentId = $students[count($students) - 1]->id;
        unset($students[count($students) - 1]); //Expelled!
        $this->objectRepository->saveEntity($afflictedCourse);
        //Check that we only have one student in the course
        $afflictedCourse = $this->objectRepository->find(4);
        $remainingStudents = $afflictedCourse->students;
        $this->assertEquals(1, count($remainingStudents));
        //Check that the expelled student has not actually been deleted (because it is not an orphan)
        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $zombieStudent = $this->objectRepository->find($unrulyStudentId);
        $this->assertNotNull($zombieStudent);
        $this->assertEquals($unrulyStudentId, $zombieStudent->id);

        //Remove a child entity which has orphan removal and child is really an orphan - it should be deleted
        $this->setUp(); //Forget about anything added by previous tests
        $this->objectRepository->setClassName(TestCourseOrphan::class);
        $afflictedCourse = $this->objectRepository->find(4);
        $students =& $afflictedCourse->students;
        $unrulyStudentId = $students[0]->id;
        unset($students[0]); //Expelled!
        $this->objectRepository->saveEntity($afflictedCourse);
        //Check that we only have one student
        $afflictedCourse = $this->objectRepository->find(4);
        $remainingStudents = $afflictedCourse->students;
        $this->assertEquals(1, count($remainingStudents));
        //Check that the expelled student has really gone
        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $zombieStudent = $this->objectRepository->find($unrulyStudentId);
        $this->assertEquals(null, $zombieStudent);

        //Replace a child of an existing entity that is a property of a new entity
        //(ensure new entity inserted, existing entity updated, orphan entity deleted)
        //Eg. create a new student, assign an existing course to it, update a property on the course,
        //remove one of the course's students, then add another new student, then save the original new student.
        //Removed student should be deleted, two new students should be inserted, course should be updated.
        $this->setUp(); //Forget about anything added by previous tests
        $this->objectRepository->setClassName(TestCourseOrphan::class);
        $existingCourse = $this->objectRepository->find(4);
        $existingStudents = $existingCourse->students;
        $this->assertEquals('Shard', $existingStudents[1]->lastName); //Make sure the pet we want to replace is there
        $this->assertEquals(8, $existingStudents[1]->id);
        $newStudent = new TestStudentOrphan();
        $newStudent->firstName = 'Arthur';
        $newStudent->courses[] = $existingCourse;
        $existingCourse->students[] = $newStudent; //Would normally be handled by the entities, but whatever
        $newStudent->courses[0]->name = 'Updated Course Name';
        $newStudent2 = new TestStudentOrphan();
        $newStudent2->firstName = 'Sam';
        $newStudent2->iq = 126;
        $newStudent2->courses[] = $existingCourse; //Would normally be handled by the entities, but whatever
        $courseStudents =& $newStudent->courses[0]->students;
        $replacedStudentId = $courseStudents[0]->id;
        $courseStudents[0] = $newStudent2;
        $this->objectRepository->saveEntity($newStudent);

        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $refreshedStudent = $this->objectRepository->find($newStudent->id);
        $this->assertEquals('Arthur', $refreshedStudent->firstName);
        $this->assertEquals($existingCourse->id, $refreshedStudent->courses[0]->id);
        $this->assertEquals('Updated Course Name', $refreshedStudent->courses[0]->name);
        $refreshedStudents = $refreshedStudent->courses[0]->students;
        $this->assertEquals(3, count($refreshedStudents));
        $studentNames = array_column($refreshedStudent->courses[0]->students, 'firstName');
        $this->assertContains('Sam', $studentNames);
        $this->assertContains('Arthur', $studentNames);
        $this->assertNotContains('Obadiah', $studentNames);
        //Make sure orphan was deleted
        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $deadStudent = $this->objectRepository->find($replacedStudentId);
        $this->assertNull($deadStudent);
    }

    public function doOrphanRemovalOnDeleteTest()
    {
        $this->testName = 'Orphan removal on delete';
        $this->setUp();
        //Delete a parent entity: child should be deleted only if it is orphaned.
        $this->objectRepository->setClassName(TestCourseOrphan::class);
        $obsoleteCourse = $this->objectRepository->find(4);
        $studentAtRiskId = $obsoleteCourse->students[1]->id;
        $this->objectRepository->deleteEntity($obsoleteCourse);
        $zombieCourse = $this->objectRepository->find(4);
        $this->assertEquals(null, $zombieCourse); //Make sure parent is really dead
        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $orphan = $this->objectRepository->find($studentAtRiskId);
        $this->assertEquals($studentAtRiskId, $orphan->id); //Make sure child was loaded
        $orphanCourses = $orphan->courses;
        $this->assertEquals(1, count($orphanCourses)); //Make sure child is an orphan
        //Will need to hit the database directly to see if relationship has been deleted from bridging table
        $sql = "SELECT course_id FROM student_course WHERE course_id = 4";
        $stm = $this->pdo->prepare($sql);
        $stm->execute();
        $courseId = $stm->fetchColumn();
        $this->assertEquals(null, $courseId);

        //Now delete the remaining course, causing the student to be orphaned, and therefore deleted
        $this->objectRepository->setClassName(TestCourseOrphan::class);
        $obsoleteCourse = $this->objectRepository->find(3);
        $studentsAtRisk = $obsoleteCourse->students;
        $this->objectRepository->deleteEntity($obsoleteCourse);
        $zombieCourse = $this->objectRepository->find(3);
        $this->assertEquals(null, $zombieCourse); //Make sure parent is really dead
        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $zombieStudent = $this->objectRepository->find($studentAtRiskId);
        $this->assertEquals(null, $zombieStudent);
        foreach ($studentsAtRisk as $newStudentAtRisk) {
            $student = $this->objectRepository->find($newStudentAtRisk->id);
            if ($newStudentAtRisk->id != $studentAtRiskId) {
                $this->assertEquals($newStudentAtRisk->id, $student->id);
            } else {
                $this->assertNull($student);
            }
        }
    }

    public function doSuppressedOrphanRemovalTest()
    {
        $this->setUp();
        $this->testName = 'Suppressed orphan removal';
        $this->objectRepository->setClassName(TestCourseOrphan::class);

        //Create an orphan by deleting course 3, leaving student 8 with only one course (4)
        $course3 = $this->objectRepository->getObjectReference(TestCourseOrphan::class, ['id' => 3]);
        $deleteCount = $this->objectRepository->deleteEntity($course3);
        $this->assertEquals(7, $deleteCount);

        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_RELATIONSHIPS, true);
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, true);

        $obsoleteCourse = $this->objectRepository->find(4);
        //Remove a child entity which has orphan removal - it should NOT be deleted
        $students = $obsoleteCourse->students;
        $studentAtRisk = $students[0]->id;
        unset($students[count($students) - 1]); //Expelled!
        $obsoleteCourse->students = $students;
        $this->objectRepository->saveEntity($obsoleteCourse);

        //Check that the naughty student still exists
        $this->objectRepository->setClassName(TestStudentOrphan::class);
        $zombieStudent = $this->objectRepository->find($studentAtRisk);
        $this->assertEquals($studentAtRisk, $zombieStudent->id);

        //And still belongs to the parent
        $this->objectRepository->setClassName(TestCourseOrphan::class);
        $this->objectRepository->clearCache(); //Necessary to re-load as the orphan removal was suppressed.
        $refreshedCourse = $this->objectRepository->find(4);
        $remainingStudents = $refreshedCourse->students;
        $this->assertEquals(2, count($remainingStudents));
    }
}
