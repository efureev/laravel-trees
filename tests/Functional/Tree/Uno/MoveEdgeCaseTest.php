<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class MoveEdgeCaseTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function appendToOwnDescendantThrows(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        $root->refresh();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        // Move the root into its own descendant.
        $root->appendTo($child)->save();
    }

    #[Test]
    public function prependToOwnDescendantThrows(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        /** @var Category $grandChild */
        $grandChild = static::model(['title' => 'grand child']);
        $grandChild->appendTo($child)->save();

        $root->refresh();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is child.');

        // Move the root into its own grand-child.
        $root->prependTo($grandChild)->save();
    }

    #[Test]
    public function appendToSameTargetThrows(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node when the target node is same.');

        $root->appendTo($root)->save();
    }

    #[Test]
    public function reAppendToCurrentParentKeepsBoundsConsistent(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($root)->save();

        /** @var Category $node2 */
        $node2 = static::model(['title' => 'node 2']);
        $node2->appendTo($root)->save();

        $root->refresh();
        $node1->refresh();
        $node2->refresh();

        static::assertEquals(1, $root->leftValue());
        static::assertEquals(6, $root->rightValue());

        // Re-append node1 to its current parent (no structural change expected).
        $node1->refresh();
        $node1->appendTo($root)->save();

        $root->refresh();
        $node1->refresh();
        $node2->refresh();

        // Bounds must remain a valid, non-overlapping nested set.
        static::assertEquals(1, $root->leftValue());
        static::assertEquals(6, $root->rightValue());
        static::assertSame(1, $node1->levelValue());
        static::assertSame(1, $node2->levelValue());
        static::assertTrue($node1->isChildOf($root));
        static::assertTrue($node2->isChildOf($root));
        static::assertCount(2, $root->children);

        // Bounds did not drift: node1 keeps its original slot, node2 keeps its own.
        static::assertEquals(2, $node1->leftValue());
        static::assertEquals(3, $node1->rightValue());
        static::assertEquals(4, $node2->leftValue());
        static::assertEquals(5, $node2->rightValue());
    }
}
