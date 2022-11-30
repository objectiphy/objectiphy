<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\UnitTests\Cache;

use Objectiphy\Objectiphy\Tests\Entity\TestAddress;
use Psr\SimpleCache\CacheInterface;
use Objectiphy\Objectiphy\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    private CacheInterface $cache;

    public function setUp(): void
    {
        parent::setUp();
        $this->cache = new FileCache(sys_get_temp_dir());
    }

    public function testSetAndGet()
    {
        $value = rand(0, 500);
        $object = new TestAddress();
        $object->setTown('Moon13');
        $array = [1, '123', ['associative' => 1.23]];

        $this->cache->set('randomItem', $value);
        $this->cache->set('randomItem2', $object);

        $cacheValue = $this->cache->get('randomItem', 'default');
        $this->assertSame($value, $cacheValue);

        $cacheValue2 = $this->cache->get('somethingMissing', 'default');
        $this->assertSame('default', $cacheValue2);

        $cacheValue3 = $this->cache->get('randomItem', 'default', 500); //ttl should be silently ignored
        $this->assertSame($value, $cacheValue3);

        $cacheValue4 = $this->cache->get('randomItem2');
        $this->assertEquals($object, $cacheValue4);
        $this->assertEquals('Moon13', $cacheValue4->getTown());

        $cacheValue5 = $this->cache->get('somethingMissing');
        $this->assertNull($cacheValue5);

        $this->cache->set('randomItem2', 'irrelevant', 0);
        $this->assertSame(null, $this->cache->get('randomItem2'));

        $this->cache->set('---', $array);
        $this->assertSame($array, $this->cache->get('---'));

        $this->cache->set('null', null);
        $this->assertTrue($this->cache->has('null'));
        $this->assertNull($this->cache->get('null'));
    }

    public function testDeleteAndHas()
    {
        $value = rand(0, 500);
        $this->cache->set('randomItem', $value);
        $this->cache->set('randomItem2', $value + 100);

        //Ensure the items are there
        $cacheValue = $this->cache->get('randomItem');
        $this->assertEquals($value, $cacheValue);
        $cacheValue2 = $this->cache->get('randomItem2');
        $this->assertEquals($value + 100, $cacheValue2);

        //Delete one of them and make sure it has gone
        $this->cache->delete('randomItem');
        $this->assertEquals(false, $this->cache->has('randomItem'));
        $cacheValue3 = $this->cache->get('randomItem');
        $this->assertNull($cacheValue3);

        //Make sure the other one is still there
        $this->assertEquals(true, $this->cache->has('randomItem2'));
        $cacheValue4 = $this->cache->get('randomItem2');
        $this->assertEquals($value + 100, $cacheValue4);

        //Might as well delete that too
        $this->cache->delete('randomItem2');
        $this->assertEquals(false, $this->cache->has('randomItem2'));
        $cacheValue5 = $this->cache->get('randomItem2', 'default');
        $this->assertEquals('default', $cacheValue5);
    }

    public function testClear()
    {
        //Ensure any non-cache files are left alone
        $value = rand(0, 500);
        $this->cache->set('randomItem', $value);
        $this->cache->set('randomItem2', $value + 100);
        $this->cache->set('randomItem3', $value + 999);
        file_put_contents(sys_get_temp_dir() . '/sometext.txt', 'test');
        file_put_contents(sys_get_temp_dir() . '/obj_cacheMissingUnderscore.txt', 'test2');

        $this->assertEquals(true, $this->cache->has('randomItem'));
        $this->assertEquals(true, $this->cache->has('randomItem2'));
        $this->assertEquals(true, $this->cache->has('randomItem3'));
        $this->cache->clear();
        $this->assertEquals(false, $this->cache->has('randomItem'));
        $this->assertEquals(false, $this->cache->has('randomItem2'));
        $this->assertEquals(false, $this->cache->has('randomItem3'));

        $this->assertEquals(true, file_exists(sys_get_temp_dir() . '/sometext.txt'));
        $this->assertEquals(true, file_exists(sys_get_temp_dir() . '/obj_cacheMissingUnderscore.txt'));
        $this->assertEquals('test', file_get_contents(sys_get_temp_dir() . '/sometext.txt'));
        $this->assertEquals('test2', file_get_contents(sys_get_temp_dir() . '/obj_cacheMissingUnderscore.txt'));

        //Tidy up
        unlink(sys_get_temp_dir() . '/sometext.txt');
        unlink(sys_get_temp_dir() . '/obj_cacheMissingUnderscore.txt');
    }

    public function testGetMultiple()
    {
        $value = rand(0, 500);
        $this->cache->set('randomItem', $value);
        $this->cache->set('randomItem2', $value + 100);
        $this->cache->set('randomItem3', $value + 999);

        $cacheValues = $this->cache->getMultiple(['randomItem', 'missingItem', 'randomItem3'], 'default');
        $this->assertEquals(
            [
                'randomItem' => $value,
                'missingItem' => 'default',
                'randomItem3' => $value + 999
            ],
            $cacheValues
        );
    }

    public function testSetMultiple()
    {
        $value = rand(0, 500);
        $this->cache->setMultiple(
            [
              'randomItem' => $value,
              'randomItem2' => $value + 100,
              'randomItem3' => $value + 999
            ]
        );

        $this->assertEquals($value, $this->cache->get('randomItem'));
        $this->assertEquals($value + 100, $this->cache->get('randomItem2'));
        $this->assertEquals($value + 999, $this->cache->get('randomItem3'));
    }

    public function testDeleteMultiple()
    {
        $value = rand(0, 500);
        $this->cache->setMultiple(
            [
                'randomItem' => $value,
                'randomItem2' => $value + 100,
                'randomItem3' => $value + 999
            ]
        );

        $this->cache->deleteMultiple(['randomItem', 'randomItem3']);
        $this->assertNull($this->cache->get('randomItem'));
        $this->assertEquals($value + 100, $this->cache->get('randomItem2'));
        $this->assertNull($this->cache->get('randomItem3'));
    }

    public function testBadKey()
    {
        $exceptionThrown = false;
        try {
            $this->cache->set('invalid{}', 'vomit');
        } catch (\Throwable $ex) {
            $exceptionThrown = true;
        } finally {
            $this->assertTrue($exceptionThrown);
        }

        $exceptionThrown = false;
        try {
            $this->cache->set('inval\\id', 'vomit');
        } catch (\Throwable $ex) {
            $exceptionThrown = true;
        } finally {
            $this->assertTrue($exceptionThrown);
        }

        $exceptionThrown = false;
        try {
            $this->cache->set('valid_.+&!$^,', 'lemonade');
        } catch (\Throwable $ex) {
            $exceptionThrown = true;
        } finally {
            $this->assertFalse($exceptionThrown);
        }
    }
}
