<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Tests\models\v5\ArchivedCategory;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\CustomColumnsCategory;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class UseConfigShorterTest extends AbstractUnitTestCase
{
    #[Test]
    public function attributeShortcutsForUnoTree(): void
    {
        $model = new Category(['title' => 'node']);

        static::assertInstanceOf(Attribute::class, $model->leftAttribute());
        static::assertInstanceOf(Attribute::class, $model->rightAttribute());
        static::assertInstanceOf(Attribute::class, $model->levelAttribute());
        static::assertInstanceOf(Attribute::class, $model->parentAttribute());

        static::assertSame(AttributeType::Left, $model->leftAttribute()->name());
        static::assertSame(AttributeType::Right, $model->rightAttribute()->name());
        static::assertSame(AttributeType::Level, $model->levelAttribute()->name());
        static::assertSame(AttributeType::Parent, $model->parentAttribute()->name());

        static::assertSame(AttributeType::Left->value, (string)$model->leftAttribute());
        static::assertSame(AttributeType::Right->value, (string)$model->rightAttribute());
        static::assertSame(AttributeType::Level->value, (string)$model->levelAttribute());
        static::assertSame(AttributeType::Parent->value, (string)$model->parentAttribute());

        static::assertNull($model->treeAttribute());
        static::assertNull($model->treeValue());
        static::assertFalse($model->isMulti());
    }

    #[Test]
    public function attributeShortcutsForMultiTree(): void
    {
        $model = new MultiCategory(['title' => 'node']);

        static::assertInstanceOf(Attribute::class, $model->treeAttribute());
        static::assertSame(AttributeType::Tree, $model->treeAttribute()->name());
        static::assertSame(AttributeType::Tree->value, (string)$model->treeAttribute());
        static::assertTrue($model->isMulti());

        $model->tree_id = 5;
        static::assertSame(5, $model->treeValue());
    }

    #[Test]
    public function valueShortcutsReadModelAttributes(): void
    {
        $model            = new Category(['title' => 'node']);
        $model->lft       = 3;
        $model->rgt       = 8;
        $model->lvl       = 2;
        $model->parent_id = 1;

        static::assertSame(3, $model->leftValue());
        static::assertSame(8, $model->rightValue());
        static::assertSame(2, $model->levelValue());
        static::assertSame(1, $model->parentValue());
    }

    #[Test]
    public function isRootDependsOnParentValue(): void
    {
        $root            = new Category(['title' => 'root']);
        $root->parent_id = null;
        static::assertTrue($root->isRoot());

        $child            = new Category(['title' => 'child']);
        $child->parent_id = 1;
        static::assertFalse($child->isRoot());
    }

    #[Test]
    public function isLevelChecksLevelValue(): void
    {
        $model      = new Category(['title' => 'node']);
        $model->lvl = 2;

        static::assertTrue($model->isLevel(2));
        static::assertFalse($model->isLevel(0));
        static::assertFalse($model->isLevel(3));
    }

    #[Test]
    public function isEqualToComparesBounds(): void
    {
        $a            = new Category(['title' => 'a']);
        $a->lft       = 1;
        $a->rgt       = 2;
        $a->lvl       = 0;
        $a->parent_id = null;

        $b            = new Category(['title' => 'b']);
        $b->lft       = 1;
        $b->rgt       = 2;
        $b->lvl       = 0;
        $b->parent_id = null;

        static::assertTrue($a->isEqualTo($b));

        $b->rgt = 4;
        static::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function getBoundsReturnsColumnValuesForUnoTree(): void
    {
        $model            = new Category(['title' => 'node']);
        $model->lft       = 1;
        $model->rgt       = 6;
        $model->lvl       = 0;
        $model->parent_id = null;

        static::assertSame([1, 6, 0, null], $model->getBounds());
    }

    #[Test]
    public function getBoundsIncludesTreeForMultiTree(): void
    {
        $model            = new MultiCategory(['title' => 'node']);
        $model->lft       = 1;
        $model->rgt       = 6;
        $model->lvl       = 0;
        $model->parent_id = null;
        $model->tree_id   = 7;

        static::assertSame([1, 6, 0, null, 7], $model->getBounds());
    }

    #[Test]
    public function isSoftDeleteReflectsConfig(): void
    {
        static::assertFalse((new Category(['title' => 'node']))->getTreeConfig()->isSoftDelete);
        static::assertTrue((new ArchivedCategory(['title' => 'node']))->getTreeConfig()->isSoftDelete);
    }

    #[Test]
    public function shortcutsRespectCustomColumnNames(): void
    {
        $model = new CustomColumnsCategory(['title' => 'node']);

        static::assertSame('left_bound', (string)$model->leftAttribute());
        static::assertSame('right_bound', (string)$model->rightAttribute());
        static::assertSame('depth', (string)$model->levelAttribute());
        static::assertSame('pid', (string)$model->parentAttribute());

        $model->setAttribute('left_bound', 4);
        $model->setAttribute('right_bound', 9);
        $model->setAttribute('depth', 1);
        $model->setAttribute('pid', 2);

        static::assertSame(4, $model->leftValue());
        static::assertSame(9, $model->rightValue());
        static::assertSame(1, $model->levelValue());
        static::assertSame(2, $model->parentValue());

        static::assertSame([4, 9, 1, 2], $model->getBounds());
    }
}
