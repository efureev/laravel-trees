<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class MoveTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function moveWithinSameTree(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        /** @var MultiCategory $node2 */
        $node2 = static::model(['title' => 'node 2']);
        $node2->appendTo($root)->save();

        /** @var MultiCategory $node3 */
        $node3 = static::model(['title' => 'node 3']);
        $node3->appendTo($node2)->save();

        $root->refresh();
        $node2->refresh();
        $node3->refresh();

        static::assertEquals(2, $node3->levelValue());
        static::assertTrue($node3->isChildOf($node2));

        // Move node3 to root
        $node3->appendTo($root)->save();
        $node3->refresh();
        $node2->refresh();
        $root->refresh();

        static::assertEquals(1, $node3->levelValue());
        static::assertTrue($node3->isChildOf($root));
        static::assertEquals($root->tree_id, $node3->tree_id);
        static::assertCount(2, $root->children);
        static::assertCount(0, $node2->children);
    }

    #[Test]
    public function moveBetweenTrees(): void
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

        static::assertEquals($root1->tree_id, $node->tree_id);

        // Move to root2
        $node->appendTo($root2)->save();
        $node->refresh();
        $root1->refresh();
        $root2->refresh();

        static::assertEquals($root2->tree_id, $node->tree_id);
        static::assertTrue($node->isChildOf($root2));
        static::assertCount(0, $root1->children);
        static::assertCount(1, $root2->children);
    }

    #[Test]
    public function moveSubTreeBetweenTreesKeepsDescendants(): void
    {
        /** @var MultiCategory $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategory $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        /** @var MultiCategory $node2 */
        $node2 = static::model(['title' => 'node 2']);
        $node2->appendTo($root1)->save();

        /** @var MultiCategory $node3 */
        $node3 = static::model(['title' => 'node 3']);
        $node3->appendTo($node2)->save();

        // Refresh the moving node so its bounds reflect the appended descendant.
        $node2->refresh();

        // Move the whole sub-tree (node2 + node3) into root2.
        $node2->appendTo($root2)->save();

        $root1->refresh();
        $root2->refresh();
        $node2->refresh();
        $node3->refresh();

        static::assertEquals($root2->tree_id, $node2->tree_id);
        static::assertEquals($root2->tree_id, $node3->tree_id);
        static::assertTrue($node2->isChildOf($root2));
        static::assertTrue($node3->isChildOf($node2));
        static::assertEquals(1, $node2->levelValue());
        static::assertEquals(2, $node3->levelValue());

        static::assertCount(0, $root1->children);
        static::assertCount(1, $root2->children);
    }
}
