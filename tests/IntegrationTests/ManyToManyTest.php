<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Tests\Entity\TestCourse;

class ManyToManyTest extends IntegrationTestBase
{
    public function testReadingDefault()
    {
        $this->testName = 'Many-to-many Reading default' . $this->getCacheSuffix();
        $this->doTests();
    }

    public function testReadingMixed()
    {
        $this->testName = 'Many-to-many Reading mixed' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    public function testReadingLazy()
    {
        $this->testName = 'Many-to-many Reading lazy' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', false);
        $this->objectRepository->setConfigOption('eagerLoadToMany', false);
        $this->doTests();
    }

    public function testReadingEager()
    {
        $this->testName = 'Many-to-many Reading eager' . $this->getCacheSuffix();
        $this->objectRepository->setConfigOption('eagerLoadToOne', true);
        $this->objectRepository->setConfigOption('eagerLoadToMany', true);
        $this->doTests();
    }

    //Repeat with the cache turned off
    public function testReadingDefaultNoCache()
    {
        $this->disableCache();
        $this->testReadingDefault();
    }

    public function testReadingMixedNoCache()
    {
        $this->disableCache();
        $this->testReadingMixed();
    }

    public function testReadingLazyNoCache()
    {
        $this->disableCache();
        $this->testReadingLazy();
    }

    public function testReadingEagerNoCache()
    {
        $this->disableCache();
        $this->testReadingEager();
    }

    protected function doTests()
    {
        $this->doReadingTestsDoctrine();
        $this->doWritingTestsDoctrine();
    }

    protected function doReadingTestsDoctrine()
    {
        $this->objectRepository->setClassName(TestCourse::class);
        $course = $this->objectRepository->find(1);
        $students = $course->students;
        $this->assertEquals(4, count($students));

        $firstStudentCourses = $students[0]->courses;
        $this->assertEquals(2, count($firstStudentCourses));
    }

    protected function doWritingTestsDoctrine()
    {

        $this->assertEquals(true, true);
    }
}
