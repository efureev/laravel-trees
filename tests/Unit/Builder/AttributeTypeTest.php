<?php

namespace Fureev\Trees\Tests\Unit\Builder;

use Fureev\Trees\Config\AttributeType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AttributeType::isTreeType method
 */
class AttributeTypeTest extends TestCase
{
    public function testIsTreeTypeReturnsTrueForTreeType(): void
    {
        $attribute = AttributeType::Tree;

        $this->assertTrue($attribute->isTreeType());
    }

    public function testIsTreeTypeReturnsFalseForLeftType(): void
    {
        $attribute = AttributeType::Left;

        $this->assertFalse($attribute->isTreeType());
    }

    public function testIsTreeTypeReturnsFalseForRightType(): void
    {
        $attribute = AttributeType::Right;

        $this->assertFalse($attribute->isTreeType());
    }

    public function testIsTreeTypeReturnsFalseForLevelType(): void
    {
        $attribute = AttributeType::Level;

        $this->assertFalse($attribute->isTreeType());
    }

    public function testIsTreeTypeReturnsFalseForParentType(): void
    {
        $attribute = AttributeType::Parent;

        $this->assertFalse($attribute->isTreeType());
    }
}