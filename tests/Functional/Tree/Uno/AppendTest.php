<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class AppendTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function append(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        // Level 2
        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();
        $modelRoot->refresh();

        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(3, $node21->rightValue());
        static::assertCount(1, $node21->parents());

        $_root = $node21->parent()->first();

        static::assertTrue($_root->isRoot());
        static::assertTrue($modelRoot->isEqualTo($_root));


        // Level 3
        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 2.2']);
        $node31->appendTo($modelRoot)->save();

        $node21->refresh();
        $modelRoot->refresh();

        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(3, $node21->rightValue());

        static::assertSame(1, $node31->levelValue());
        static::assertEquals(4, $node31->leftValue());
        static::assertEquals(5, $node31->rightValue());
        static::assertCount(1, $node31->parents());

        static::assertTrue($node31->isLeaf());
        static::assertTrue($node21->isLeaf());

        $_root = $node31->getRoot();

        static::assertTrue($_root->isRoot());
        static::assertTrue($modelRoot->isEqualTo($_root));
    }

    #[Test]
    public function appendInSubLevel(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        // Level 2
        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        // Level 3
        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 2.2']);
        $node31->appendTo($node21)->save();

        $node21->refresh();
        $modelRoot->refresh();

        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());

        static::assertSame(2, $node31->levelValue());
        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());
        static::assertCount(2, $node31->parents());

        static::assertTrue($node31->isLeaf());
        static::assertFalse($modelRoot->isLeaf());
        static::assertFalse($node21->isLeaf());

        $_root = $node31->getRoot();

        static::assertTrue($_root->isRoot());
        static::assertTrue($modelRoot->isEqualTo($_root));
    }

    #[Test]
    public function appendToSameException(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $this->expectException(Exception::class);

        $modelRoot->appendTo($modelRoot)->save();
    }

    #[Test]
    public function appendToNonExistParentException(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node'])->makeRoot();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);

        $this->expectException(Exception::class);
        $node21->appendTo($modelRoot)->save();
    }

    #[Test]
    public function moveToSelfChildrenException(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node'])->makeRoot();
        $modelRoot->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $node21->refresh();
        static::assertTrue($node31->isChildOf($node21));

        $this->expectException(Exception::class);
        $node21->appendTo($node31)->save();
    }
}
