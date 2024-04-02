<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class ChildrenTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function children(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 2.2']);
        /** @var Category $node23 */
        $node23 = static::model(['title' => 'child 2.3']);

        /** @var Category $node221 */
        $node221 = static::model(['title' => 'child 2.2.1']);
        /** @var Category $node2211 */
        $node2211 = static::model(['title' => 'child 2.2.1.1']);

        $node21->appendTo($modelRoot)->save();
        $node22->appendTo($modelRoot)->save();
        $node23->appendTo($modelRoot)->save();

        $node221->appendTo($node22)->save();
        $node2211->appendTo($node221)->save();

        $node221->refresh();
        $node21->refresh();
        $node22->refresh();
        $modelRoot->refresh();

        static::assertCount(0, $node21->children);
        static::assertCount(0, $node23->children);
        static::assertCount(1, $node22->children);
        static::assertCount(1, $node221->children()->get());
        static::assertCount(3, $modelRoot->children);
    }

    #[Test]
    public function saveChildren(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);

        $modelRoot->children()->save($node21);
        $modelRoot->refresh();

        static::assertEquals(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());
        static::assertEquals(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(3, $node21->rightValue());
    }

    #[Test]
    public function createAnyChildrenTree(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);

        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 2.2']);

        $modelRoot->children()->save($node21);
        $node21->children()->save($node22);

        static::assertEquals(0, $modelRoot->levelValue());
        static::assertEquals(1, $node21->levelValue());
        static::assertEquals(2, $node22->levelValue());

        $modelRoot->refresh();
        $node21->refresh();
        $node22->refresh();

        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());
        static::assertEquals(3, $node22->leftValue());
        static::assertEquals(4, $node22->rightValue());
    }
}
