<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit\Builder;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Tests\AbstractTestCase;

class AttributeTest extends AbstractTestCase
{
    public function testCreateAttribute(): void
    {
        $attr = Attribute::make(AttributeType::Left);

        static::assertEquals(AttributeType::Left, $attr->name());
        static::assertEquals(AttributeType::Left->value, $attr->name()->value);
        static::assertEquals(AttributeType::Left->value, (string)$attr);
        static::assertNull($attr->default());
        static::assertEquals(AttributeType::Left->value, $attr->columnName());
        static::assertFalse($attr->nullable());
        static::assertEquals(FieldType::UnsignedInteger, $attr->type());
    }

    public function testChangeColumnName(): void
    {
        $attr = Attribute::make(AttributeType::Left);
        static::assertEquals(AttributeType::Left->value, $attr->columnName());

        $attr->setColumnName('test');
        static::assertEquals('test', $attr->columnName());
    }

    /**
     * The package writes `(string)$attribute` wherever a column name is wanted. That spelling is
     * only safe while it stays identical to `columnName()`, renamed attributes included.
     */
    public function testStringifiesToItsColumnName(): void
    {
        $attr = Attribute::make(AttributeType::Left);
        static::assertSame($attr->columnName(), (string)$attr);

        $attr->setColumnName('left_bound');
        static::assertSame('left_bound', (string)$attr);
        static::assertSame($attr->columnName(), (string)$attr);
    }

    public function testChangeName(): void
    {
        $attr = Attribute::make(AttributeType::Left);
        static::assertSame(AttributeType::Left, $attr->name());
        static::assertSame(AttributeType::Left->value, (string)$attr);

        $attr->setName(AttributeType::Right);

        static::assertSame(AttributeType::Right, $attr->name());

        // The column name follows the attribute name while none was set explicitly.
        static::assertSame(AttributeType::Right->value, (string)$attr);

        $attr->setColumnName('kept');
        $attr->setName(AttributeType::Level);

        // Once set explicitly it stops following.
        static::assertSame('kept', (string)$attr);
    }

    public function testChangeDefault(): void
    {
        $attr = Attribute::make(AttributeType::Left);

        static::assertNull($attr->default());

        $attr->setDefault(0);
        static::assertEquals(0, $attr->default());
    }
}
