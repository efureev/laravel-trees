<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Concerns\CountsStatements;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs the table of docs/Performance.md for a multi-tree model: the rows a single tree cannot
 * reach, and the rows shared with it — scoping every query by tree changes what the statements
 * say, not how many of them there are.
 */
class DocumentedPerformanceCountsTest extends AbstractFunctionalTreeTestCase
{
    use CountsStatements;

    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * root -> (a -> (a1 -> a11, a2), b), in the tree of the given id.
     *
     * @return array<string, MultiCategory>
     */
    private function buildTree(int $tree): array
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => "root-$tree"]);
        $root->setTree($tree)->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['a', 'b'] as $title) {
            /** @var MultiCategory $node */
            $node = static::model(['title' => "$title-$tree"]);
            $node->appendTo($root->refresh())->save();
            $nodes[$title] = $node->refresh();
        }

        foreach (['a1', 'a2'] as $title) {
            /** @var MultiCategory $node */
            $node = static::model(['title' => "$title-$tree"]);
            $node->appendTo($nodes['a']->refresh())->save();
            $nodes[$title] = $node->refresh();
        }

        /** @var MultiCategory $leaf */
        $leaf = static::model(['title' => "a11-$tree"]);
        $leaf->appendTo($nodes['a1']->refresh())->save();
        $nodes['a11'] = $leaf;

        foreach ($nodes as $key => $node) {
            $nodes[$key] = $node->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function readingASubtreeCostsOne(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(1, $this->statements(static fn() => $nodes['a']->descendants()->get()));
    }

    #[Test]
    public function readingTheAncestorsCostsOne(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(1, $this->statements(static fn() => $nodes['a11']->ancestors()->get()));
    }

    #[Test]
    public function readingTheChildrenCostsOne(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(1, $this->statements(static fn() => $nodes['a']->children()->get()));
    }

    #[Test]
    public function insertingUnderAParentCostsThree(): void
    {
        $nodes = $this->buildTree(1);

        $insert = static function () use ($nodes): void {
            /** @var MultiCategory $node */
            $node = static::model(['title' => 'fresh']);
            $node->appendTo($nodes['b'])->save();
        };

        static::assertSame(3, $this->statements($insert));
    }

    /**
     * A tree id of its own is all a root needs, and `setTree()` already carries one, so nothing
     * has to be read before the insert.
     */
    #[Test]
    public function insertingARootWithAGivenTreeIdCostsOne(): void
    {
        $insert = static function (): void {
            /** @var MultiCategory $root */
            $root = static::model(['title' => 'root']);
            $root->setTree(42)->makeRoot()->save();
        };

        static::assertSame(1, $this->statements($insert));
    }

    /**
     * Without one, the generator reads the highest tree id in use to pick the next.
     */
    #[Test]
    public function insertingARootWithAGeneratedTreeIdCostsTwo(): void
    {
        $this->buildTree(1);

        $insert = static function (): void {
            /** @var MultiCategory $root */
            $root = static::model(['title' => 'root']);
            $root->makeRoot()->save();
        };

        static::assertSame(2, $this->statements($insert));
    }

    #[Test]
    public function movingInsideTheTreeCostsFive(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(5, $this->statements(static fn() => $nodes['a1']->appendTo($nodes['b'])->save()));
    }

    /**
     * The same five, and the tree column travels with the subtree rather than being written
     * node by node.
     */
    #[Test]
    public function movingToAnotherTreeCostsFive(): void
    {
        $one = $this->buildTree(1);
        $two = $this->buildTree(2);

        static::assertSame(5, $this->statements(static fn() => $one['a1']->appendTo($two['b'])->save()));

        static::assertSame(2, $one['a1']->refresh()->treeValue());
        static::assertSame(2, $one['a11']->refresh()->treeValue(), 'the descendants travel with the node');
    }

    /**
     * Re-parent the row, move the subtree into a tree of its own, close the gap it left — and
     * one select on top, for the tree id the promoted node is given.
     */
    #[Test]
    public function promotingToARootCostsFour(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(4, $this->statements(static fn() => $nodes['a']->makeRoot()->save()));
    }

    /**
     * Name the tree and that select is gone.
     */
    #[Test]
    public function promotingToARootWithAGivenTreeIdCostsThree(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(3, $this->statements(static fn() => $nodes['a']->setTree(77)->makeRoot()->save()));
    }

    #[Test]
    public function deletingALeafCostsThree(): void
    {
        $nodes = $this->buildTree(1);

        static::assertSame(3, $this->statements(static fn() => $nodes['a11']->delete()));
    }
}
