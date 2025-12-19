<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit\Config;

use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Unit\AbstractUnitTestCase;

class FieldTypeTest extends AbstractUnitTestCase
{
    public function testIsInteger(): void
    {
        $this->assertTrue(FieldType::UnsignedInteger->isInteger());
        $this->assertTrue(FieldType::UnsignedSmallInteger->isInteger());
        $this->assertTrue(FieldType::UnsignedMediumInteger->isInteger());
        $this->assertTrue(FieldType::UnsignedBigInteger->isInteger());

        $this->assertFalse(FieldType::UUID->isInteger());
        $this->assertFalse(FieldType::ULID->isInteger());
    }

    public function testFromString(): void
    {
        $this->assertEquals(FieldType::UnsignedInteger, FieldType::fromString('int'));
        $this->assertEquals(FieldType::UUID, FieldType::fromString('uuid'));
        $this->assertEquals(FieldType::ULID, FieldType::fromString('ulid'));
        $this->assertEquals(FieldType::UUID, FieldType::fromString('string'));
    }

    public function testFromStringException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid type: unknown');

        FieldType::fromString('unknown');
    }

    public function testToModelCast(): void
    {
        $this->assertEquals('integer', FieldType::UnsignedInteger->toModelCast());
        $this->assertEquals('integer', FieldType::UnsignedSmallInteger->toModelCast());
        $this->assertEquals('integer', FieldType::UnsignedMediumInteger->toModelCast());
        $this->assertEquals('integer', FieldType::UnsignedBigInteger->toModelCast());

        $this->assertEquals('string', FieldType::UUID->toModelCast());
        $this->assertEquals('string', FieldType::ULID->toModelCast());
    }
}
