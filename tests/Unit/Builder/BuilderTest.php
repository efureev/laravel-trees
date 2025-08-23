<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit\Builder;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\Builder;
use Fureev\Trees\Tests\AbstractTestCase;
use Fureev\Trees\Tests\models\v5\Category;

class BuilderTest extends AbstractTestCase
{
    public function testCreateBuilder(): void
    {
        $builder = Builder::make();
        $builder
            ->setAttributes(
                Attribute::make(AttributeType::Left),
                Attribute::make(AttributeType::Right),
                Attribute::make(AttributeType::Level),
                Attribute::make(AttributeType::Parent),
            );

        static::assertEquals(AttributeType::Left->value, (string)$builder->left());
        static::assertEquals(AttributeType::Right->value, (string)$builder->right());
        static::assertEquals(AttributeType::Parent->value, (string)$builder->parent());
        static::assertEquals(AttributeType::Level->value, (string)$builder->level());

        static::assertEquals(
            [
                AttributeType::Left->value,
                AttributeType::Right->value,
                AttributeType::Level->value,
                AttributeType::Parent->value,
            ],
            $builder->columnsNames()
        );
    }

    public function testCreateBuilderForMultiTree(): void
    {
        $builder = Builder::defaultMulti();
        static::assertNotNull($builder->tree());


        static::assertEquals(AttributeType::Right->value, (string)$builder->right());
        static::assertEquals(AttributeType::Parent->value, (string)$builder->parent());
        static::assertEquals(AttributeType::Level->value, (string)$builder->level());

        static::assertEquals(
            [
                AttributeType::Left->value,
                AttributeType::Right->value,
                AttributeType::Level->value,
                AttributeType::Parent->value,
                AttributeType::Tree->value,
            ],
            $builder->columnsNames()
        );
    }

    public function testBuildUnoConfig(): void
    {
        $builder = Builder::default();
        $config  = $builder->build(new Category());

        static::assertEquals(AttributeType::Left->value, (string)$config->left);
        static::assertEquals(AttributeType::Right->value, (string)$config->right);
        static::assertEquals(AttributeType::Parent->value, (string)$config->parent);
        static::assertEquals(AttributeType::Level->value, (string)$config->level);
        static::assertNull($config->tree);
    }

    public function testBuildConfig(): void
    {
        $builder = Builder::defaultMulti();
        $config  = $builder->build(new Category());

        static::assertEquals(AttributeType::Left->value, (string)$config->left);
        static::assertEquals(AttributeType::Right->value, (string)$config->right);
        static::assertEquals(AttributeType::Parent->value, (string)$config->parent);
        static::assertEquals(AttributeType::Level->value, (string)$config->level);
        static::assertEquals(AttributeType::Tree->value, (string)$config->tree);
    }
}
