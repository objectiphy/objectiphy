<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Tests\Entity\TestCourse;
use Objectiphy\Objectiphy\Tests\Entity\TestStudent;

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

        $this->objectRepository->clearCache();
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

        $this->objectRepository->clearCache();
        $refreshedStudent = $this->objectRepository->find($studentId);
        $this->assertEquals('7500.00', $refreshedStudent->courses[0]->cost);
    }

    protected function doDeletingTests()
    {
        $disabledCache = $this->getCacheSuffix();
        $this->setUp(); //Forget about anything added by previous tests
        if ($disabledCache) { //Have to re-do this as it will have been forgotten by setUp
            $this->disableCache();
        }

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

        $this->objectRepository->clearCache();
        $refreshedCourse = $this->objectRepository->find(1);
        $this->assertEquals(2, count($course->students));

        //TODO: test cascades and orphan removal

        
    }
}
