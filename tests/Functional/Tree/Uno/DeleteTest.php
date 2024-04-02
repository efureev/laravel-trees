<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class DeleteTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function deleteRoot(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $this->expectException(DeleteRootException::class);

        $modelRoot->delete();
    }

    #[Test]
    public function deleteNode(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->prependTo($modelRoot)->save();

        $modelRoot->refresh();
        static::assertTrue($node21->isLeaf());
        static::assertTrue($node21->isChildOf($modelRoot));
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());


        static::assertTrue($node21->delete());

        $modelRoot->refresh();
        static::assertTrue($modelRoot->isLeaf());
        static::assertEmpty($modelRoot->children()->count());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(2, $modelRoot->rightValue());
    }

    #[Test]
    public function deleteNodeWithLineChildren(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $nodeToDelete */
        $nodeToDelete = static::model(['title' => 'deletable node']);
        $nodeToDelete->appendTo($modelRoot)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($nodeToDelete)->save();

        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 2.2']);
        $node22->appendTo($nodeToDelete)->save();

        $modelRoot->refresh();
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(8, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());
        static::assertSame(3, $modelRoot->descendants()->count());

        $nodeToDelete->deleteWithChildren();

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isLeaf());
        static::assertEmpty($modelRoot->children()->count());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(2, $modelRoot->rightValue());
    }

    #[Test]
    public function deleteNodeWithMoveChildrenToParent(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $nodeToDelete */
        $nodeToDelete = static::model(['title' => 'deletable node']);
        $nodeToDelete->appendTo($modelRoot)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($nodeToDelete)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $nodeToDelete->refresh();
        $node21->refresh();
        $node31->refresh();

        static::assertEquals(2, $nodeToDelete->leftValue());
        static::assertEquals(7, $nodeToDelete->rightValue());
        static::assertEquals(1, $nodeToDelete->levelValue());

        static::assertEquals(3, $node21->leftValue());
        static::assertEquals(6, $node21->rightValue());
        static::assertEquals(2, $node21->levelValue());

        static::assertEquals(4, $node31->leftValue());
        static::assertEquals(5, $node31->rightValue());
        static::assertEquals(3, $node31->levelValue());

        // delete

        $nodeToDelete->delete();

        $modelRoot->refresh();

        static::assertCount(1, $modelRoot->children()->get());
        static::assertFalse($modelRoot->isLeaf());

        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());

        $node21->refresh();
        static::assertTrue($modelRoot->isEqualTo($node21->parent));
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());
        static::assertEquals(1, $node21->levelValue());

        $node31->refresh();
        static::assertTrue($node21->isEqualTo($node31->parent));
        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());
        static::assertEquals(2, $node31->levelValue());
    }
}
