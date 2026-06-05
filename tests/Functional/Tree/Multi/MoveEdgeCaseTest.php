<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class MoveEdgeCaseTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function appendToOwnDescendantThrows(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        /** @var MultiCategory $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        $root->refresh();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        $root->appendTo($child)->save();
    }

    #[Test]
    public function appendToNodeInAnotherTreeMovesWholeSubTree(): void
    {
        /** @var MultiCategory $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategory $branch1 */
        $branch1 = static::model(['title' => 'branch 1']);
        $branch1->appendTo($root1)->save();

        /** @var MultiCategory $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        /** @var MultiCategory $node */
        $node = static::model(['title' => 'node']);
        $node->appendTo($root2)->save();

        /** @var MultiCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($node)->save();

        $node->refresh();

        static::assertEquals($root2->tree_id, $node->tree_id);

        // Move the whole sub-tree (node + leaf) under a non-root node of another tree.
        $node->appendTo($branch1)->save();

        $node->refresh();
        $leaf->refresh();
        $branch1->refresh();
        $root1->refresh();
        $root2->refresh();

        // Entire moved sub-tree adopts target tree id.
        static::assertEquals($root1->tree_id, $node->tree_id);
        static::assertEquals($root1->tree_id, $leaf->tree_id);

        static::assertTrue($node->isChildOf($branch1));
        static::assertTrue($leaf->isChildOf($node));
        static::assertSame(2, $node->levelValue());
        static::assertSame(3, $leaf->levelValue());

        // The source tree no longer holds the moved node.
        static::assertCount(0, $root2->children);
        static::assertCount(1, $branch1->children);
    }

    #[Test]
    public function moveNodeBackAndForthBetweenTreesKeepsBoundsValid(): void
    {
        /** @var MultiCategory $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategory $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        /** @var MultiCategory $node */
        $node = static::model(['title' => 'node']);
        $node->appendTo($root1)->save();

        // root1 -> root2
        $node->appendTo($root2)->save();
        $node->refresh();
        static::assertEquals($root2->tree_id, $node->tree_id);

        // root2 -> root1 (back)
        $node->appendTo($root1)->save();
        $node->refresh();
        $root1->refresh();
        $root2->refresh();

        static::assertEquals($root1->tree_id, $node->tree_id);
        static::assertTrue($node->isChildOf($root1));
        static::assertCount(1, $root1->children);
        static::assertCount(0, $root2->children);

        // root1 stays a well-formed tree.
        static::assertEquals(1, $root1->leftValue());
        static::assertEquals(4, $root1->rightValue());
        static::assertEquals(2, $node->leftValue());
        static::assertEquals(3, $node->rightValue());

        // root2 collapses back to a single node.
        static::assertEquals(1, $root2->leftValue());
        static::assertEquals(2, $root2->rightValue());
    }
}
