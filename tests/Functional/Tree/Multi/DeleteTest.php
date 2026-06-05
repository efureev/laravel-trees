<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class DeleteTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function deleteRootInMultiTree(): void
    {
        /** @var MultiCategory $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategory $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        static::assertCount(2, MultiCategory::root()->get());

        $root1->delete();

        static::assertCount(1, MultiCategory::root()->get());
        static::assertDatabaseMissing('categories_multi', ['title' => 'root 1']);
        static::assertDatabaseHas('categories_multi', ['title' => 'root 2']);
    }

    #[Test]
    public function deleteLeafNode(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        /** @var MultiCategory $node21 */
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
        static::assertEquals($modelRoot->tree_id, $modelRoot->treeValue());
    }

    #[Test]
    public function deleteNodeWithLineChildren(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        /** @var MultiCategory $nodeToDelete */
        $nodeToDelete = static::model(['title' => 'deletable node']);
        $nodeToDelete->appendTo($modelRoot)->save();

        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($nodeToDelete)->save();

        /** @var MultiCategory $node22 */
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
        static::assertDatabaseMissing('categories_multi', ['title' => 'child 2.1']);
        static::assertDatabaseMissing('categories_multi', ['title' => 'child 2.2']);
    }

    #[Test]
    public function deleteNodeWithMoveChildrenToParent(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        /** @var MultiCategory $nodeToDelete */
        $nodeToDelete = static::model(['title' => 'deletable node']);
        $nodeToDelete->appendTo($modelRoot)->save();

        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($nodeToDelete)->save();

        /** @var MultiCategory $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $nodeToDelete->refresh();
        $node21->refresh();
        $node31->refresh();

        static::assertEquals(2, $nodeToDelete->leftValue());
        static::assertEquals(7, $nodeToDelete->rightValue());
        static::assertEquals(1, $nodeToDelete->levelValue());

        // delete
        $nodeToDelete->delete();

        $modelRoot->refresh();

        static::assertCount(1, $modelRoot->children()->get());
        static::assertFalse($modelRoot->isLeaf());

        $node21->refresh();
        static::assertTrue($modelRoot->isEqualTo($node21->parent));
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());
        static::assertEquals(1, $node21->levelValue());
        static::assertEquals($modelRoot->tree_id, $node21->tree_id);

        $node31->refresh();
        static::assertTrue($node21->isEqualTo($node31->parent));
        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());
        static::assertEquals(2, $node31->levelValue());
        static::assertEquals($modelRoot->tree_id, $node31->tree_id);
    }
}
