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

    public function testChangeDefault(): void
    {
        $attr = Attribute::make(AttributeType::Left);

        static::assertNull($attr->default());

        $attr->setDefault(0);
        static::assertEquals(0, $attr->default());
    }
}
