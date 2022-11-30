<?php

declare(strict_types=1);

namespace Objectiphy\Objectiphy\Tests\UnitTests\Database\MySql;

use Objectiphy\Objectiphy\Database\MySql\DataTypeHandlerMySql;
use Objectiphy\Objectiphy\Orm\ObjectReference;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use PHPUnit\Framework\TestCase;

class DataTypeHandlerMySqlTest extends TestCase
{
    private DataTypeHandlerMySql $handler;

    public function setUp(): void
    {
        $this->handler = new DataTypeHandlerMySql();
    }

    public function testToPersistenceValueDateTime(): void
    {
        $dateTimeString = '2022-11-05 11:13:04';
        $value = new \DateTime($dateTimeString);
        $result = $this->handler->toPersistenceValue($value);
        $this->assertEquals($dateTimeString, $value);
        $this->assertSame(true, $result);

        $now = new \DateTime();
        $value2 = $now->format('d/m/Y');
        $result = $this->handler->toPersistenceValue($value2, 'datestring', 'd/m/Y');
        $this->assertEquals($now->format('Y-m-d H:i:s'), $value2);
        $this->assertSame(true, $result);

        $value3 = new ExtendedDateTime($dateTimeString);
        $result = $this->handler->toPersistenceValue($value3);
        $this->assertEquals($dateTimeString, $value3);
        $this->assertSame(true, $result);
    }

    public function testToPersistenceValueObjectReference(): void
    {
        $objectReference = new ObjectReference(TestPolicy::class, ['id' => 123]);
        $this->handler->toPersistenceValue($objectReference);
        $this->assertEquals(123, $objectReference);
    }

    public function testToPersistenceValueBoolean(): void
    {
        $value = true;
        $this->handler->toPersistenceValue($value);
        $this->assertSame(1, $value);

        $value2 = false;
        $this->handler->toPersistenceValue($value2);
        $this->assertSame(0, $value2);

        $value3 = 'true';
        $this->handler->toPersistenceValue($value3);
        $this->assertNotSame(1, $value3);
        $this->handler->toPersistenceValue($value3, 'bool');
        $this->assertSame(1, $value3);

        $value4 = '';
        $this->handler->toPersistenceValue($value4, 'boolean');
        $this->assertSame(0, $value4);

        $value5 = null;
        $this->handler->toPersistenceValue($value5, 'bool');
        $this->assertNull($value5);
    }

    public function testToPersistenceValueInteger(): void
    {
        $value = 123;
        $this->handler->toPersistenceValue($value);
        $this->assertSame(123, $value);

        $value2 = '123';
        $this->handler->toPersistenceValue($value2);
        $this->assertNotSame(123, $value2);
        $this->handler->toPersistenceValue($value2, 'int');
        $this->assertSame(123, $value2);

        $value3 = '';
        $this->handler->toPersistenceValue($value3, 'integer');
        $this->assertSame(0, $value3);

        $value4 = null;
        $this->handler->toPersistenceValue($value4, 'int');
        $this->assertNull($value4);
    }

    public function testToPersistenceValueFailure(): void
    {
        $value = new TestPolicy();
        $result = $this->handler->toPersistenceValue($value);
        $this->assertSame(false, $result);
    }

    public function testToObjectValueDateTime(): void
    {
        $value = '2022-11-05 11:48:45';
        $result = $this->handler->toObjectValue($value);
        $this->assertSame(false, $result);
        $result2 = $this->handler->toObjectValue($value, \DateTimeImmutable::class);
        $this->assertInstanceOf(\DateTimeImmutable::class, $value);
        $this->assertSame(true, $result2);

        $value2 = '2022-11-05 11:48:45';
        $result3 = $this->handler->toObjectValue($value2, 'date_time');
        $this->assertInstanceOf(\DateTime::class, $value2);
        $this->assertSame(true, $result3);

        $value3 = '2022-11-05';
        $result4 = $this->handler->toObjectValue($value3, 'datetimestring');
        $this->assertSame('2022-11-05 00:00:00', $value3);
        $this->assertSame(true, $result4);

        $value4 = new \DateTimeImmutable();
        $this->handler->toObjectValue($value4, 'datestring');
        $this->assertSame((new \DateTime())->format('Y-m-d'), $value4);

        $value5 = 'MumboJumbo';
        $this->handler->toObjectValue($value5, 'date_time_string');
        $this->assertSame('MumboJumbo', $value5);
    }
}
