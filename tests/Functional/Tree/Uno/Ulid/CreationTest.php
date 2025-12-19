<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno\Ulid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\CategoryWithUlid;
use PHPUnit\Framework\Attributes\Test;

class CreationTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CategoryWithUlid>
     */
    protected static function modelClass(): string
    {
        return CategoryWithUlid::class;
    }

    #[Test]
    public function makeRoot(): void
    {
        $model = static::model(['title' => 'root node']);
        $model->makeRoot()->save();

        static::assertTrue($model->isRoot());
        static::assertEquals(1, $model->leftValue());
        static::assertEquals(2, $model->rightValue());
        static::assertEquals(0, $model->levelValue());
        static::assertEquals(26, strlen($model->id));
    }

    #[Test]
    public function appendTo(): void
    {
        $root = static::createRoot();

        $child1 = static::model(['title' => 'child 1']);
        $child1->appendTo($root)->save();

        $child2 = static::model(['title' => 'child 2']);
        $child2->appendTo($root)->save();

        $root->refresh();
        static::assertCount(2, $root->children);
        static::assertEquals($child1->id, $root->children[0]->id);
        static::assertEquals($child2->id, $root->children[1]->id);

        static::assertEquals(1, $root->leftValue());
        static::assertEquals(6, $root->rightValue());
        static::assertEquals(2, $child1->leftValue());
        static::assertEquals(3, $child1->rightValue());
        static::assertEquals(4, $child2->leftValue());
        static::assertEquals(5, $child2->rightValue());
    }

    #[Test]
    public function prependTo(): void
    {
        $root = static::createRoot();

        $child1 = static::model(['title' => 'child 1']);
        $child1->appendTo($root)->save();

        $child2 = static::model(['title' => 'child 2']);
        $child2->prependTo($root)->save();

        $root->refresh();
        static::assertCount(2, $root->children);
        static::assertEquals($child2->id, $root->children[0]->id);
        static::assertEquals($child1->id, $root->children[1]->id);
    }

    #[Test]
    public function insertBeforeAndAfter(): void
    {
        $root = static::createRoot();

        $child1 = static::model(['title' => 'child 1']);
        $child1->appendTo($root)->save();

        $child2 = static::model(['title' => 'child 2']);
        $child2->insertBefore($child1)->save();

        $child3 = static::model(['title' => 'child 3']);
        $child3->insertAfter($child1)->save();

        $root->refresh();
        $children = $root->children()->get();
        static::assertCount(3, $children);
        static::assertEquals('child 2', $children[0]->title);
        static::assertEquals('child 1', $children[1]->title);
        static::assertEquals('child 3', $children[2]->title);
    }
}
