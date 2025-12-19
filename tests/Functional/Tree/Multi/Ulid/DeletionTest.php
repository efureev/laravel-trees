<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\Ulid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithUlid;
use PHPUnit\Framework\Attributes\Test;

class DeletionTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategoryWithUlid>
     */
    protected static function modelClass(): string
    {
        return MultiCategoryWithUlid::class;
    }

    #[Test]
    public function deleteRootInMultiTree(): void
    {
        /** @var MultiCategoryWithUlid $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategoryWithUlid $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        static::assertCount(2, MultiCategoryWithUlid::root()->get());

        $root1->delete();

        static::assertCount(1, MultiCategoryWithUlid::root()->get());
        static::assertDatabaseMissing('multi_categories', ['title' => 'root 1']);
        static::assertDatabaseHas('multi_categories', ['title' => 'root 2']);
    }

    #[Test]
    public function deleteNodeWithMoveChildrenToParent(): void
    {
        /** @var MultiCategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        /** @var MultiCategoryWithUlid $nodeToDelete */
        $nodeToDelete = static::model(['title' => 'deletable node']);
        $nodeToDelete->appendTo($modelRoot)->save();

        /** @var MultiCategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($nodeToDelete)->save();

        /** @var MultiCategoryWithUlid $node31 */
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
