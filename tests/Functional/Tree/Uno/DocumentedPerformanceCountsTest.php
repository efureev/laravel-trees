<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Concerns\CountsStatements;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs the table of docs/Performance.md for a single-tree model. The rows that only a
 * multi-tree model can reach are in the class of the same name under Tree/Multi.
 *
 * The page used to give "5" as the cost of a move without saying which move, so the cases that
 * cost something else are asserted here as well — they are what the numbers mean.
 *
 * `up()` and `down()`, the six-statement case, are already measured by
 * Functional\DocumentedQueryCountsTest.
 */
class DocumentedPerformanceCountsTest extends AbstractFunctionalTreeTestCase
{
    use CountsStatements;

    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> (a -> (a1, a2), b, c)
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['a', 'b', 'c'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root->refresh())->save();
            $nodes[$title] = $node;
        }

        foreach (['a1', 'a2'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($nodes['a']->refresh())->save();
            $nodes[$title] = $node;
        }

        foreach ($nodes as $key => $node) {
            $nodes[$key] = $node->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function readingASubtreeCostsOne(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(1, $this->statements(static fn() => $nodes['a']->descendants()->get()));
    }

    #[Test]
    public function readingTheAncestorsCostsOne(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(1, $this->statements(static fn() => $nodes['a1']->ancestors()->get()));
    }

    #[Test]
    public function readingTheChildrenCostsOne(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(1, $this->statements(static fn() => $nodes['a']->children()->get()));
    }

    /**
     * Select the target, shift the bounds, insert.
     */
    #[Test]
    public function insertingUnderAParentCostsThree(): void
    {
        $nodes = $this->buildTree();

        $insert = static function () use ($nodes): void {
            /** @var Category $node */
            $node = static::model(['title' => 'fresh']);
            $node->appendTo($nodes['b'])->save();
        };

        static::assertSame(3, $this->statements($insert));
    }

    /**
     * A single tree holds one root, so the insert is preceded by the select that checks the
     * place is still free.
     */
    #[Test]
    public function insertingARootCostsTwo(): void
    {
        $insert = static function (): void {
            /** @var Category $root */
            $root = static::model(['title' => 'root']);
            $root->makeRoot()->save();
        };

        static::assertSame(2, $this->statements($insert));
    }

    /**
     * One select and four updates: re-parent the row, mark the subtree, shift what is in the
     * way, move the marked rows in.
     */
    #[Test]
    public function movingInsideTheTreeCostsFive(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(5, $this->statements(static fn() => $nodes['a1']->appendTo($nodes['c'])->save()));
    }

    /**
     * Nothing lies between the node and the place it is asked to take, so the shift matches no
     * rows and `shift()` returns before issuing a statement.
     */
    #[Test]
    public function movingNextToItselfSkipsTheShift(): void
    {
        $nodes = $this->buildTree();

        // `b` already sits directly before `c`.
        static::assertSame(4, $this->statements(static fn() => $nodes['b']->insertBefore($nodes['c'])->forceSave()));
    }

    /**
     * Positioning sets no attribute of its own, so a move that keeps the parent leaves the
     * model clean, and `save()` writes nothing at all — the node does not move either.
     */
    #[Test]
    public function repositioningUnderTheSameParentWritesNothing(): void
    {
        $nodes = $this->buildTree();
        $left  = $nodes['a1']->leftValue();

        static::assertSame(0, $this->statements(static fn() => $nodes['a1']->appendTo($nodes['a'])->save()));
        static::assertSame($left, $nodes['a1']->refresh()->leftValue(), 'a clean save moves nothing');
    }

    /**
     * The same move goes through when it is forced, and then costs what a move costs.
     */
    #[Test]
    public function theSameMoveForcedCostsFive(): void
    {
        $nodes = $this->buildTree();
        $left  = $nodes['a1']->leftValue();

        static::assertSame(5, $this->statements(static fn() => $nodes['a1']->appendTo($nodes['a'])->forceSave()));
        static::assertNotSame($left, $nodes['a1']->refresh()->leftValue(), 'a forced save moves the node');
    }

    /**
     * Refresh, delete, close the gap. A leaf has no children to lift, which is what makes it
     * cheaper than the seven a branch costs.
     */
    #[Test]
    public function deletingALeafCostsThree(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(3, $this->statements(static fn() => $nodes['a2']->delete()));
    }
}
