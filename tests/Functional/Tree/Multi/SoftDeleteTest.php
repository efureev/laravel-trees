<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\ArchivedMultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Soft deleting across several trees. Two conditions meet in `shift()` and nowhere else: the
 * tree column narrows the rows, `withTrashed()` widens them. Each has coverage on its own —
 * `Uno/SoftDeleteTest` for the trashed half, `Multi/ShiftTest` for the tree half — and a
 * mistake where they meet would move the bounds of a neighbouring tree, silently.
 */
class SoftDeleteTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<ArchivedMultiCategory>
     */
    protected static function modelClass(): string
    {
        return ArchivedMultiCategory::class;
    }

    /**
     * Two trees of the same shape: root(1..8) -> branch(2..5) -> leaf(3..4), aside(6..7).
     *
     * @return array<string, ArchivedMultiCategory>
     */
    private function buildTrees(): array
    {
        $nodes = [];

        foreach (['one', 'two'] as $name) {
            /** @var ArchivedMultiCategory $root */
            $root = static::model(['title' => "root $name"]);
            $root->save();

            /** @var ArchivedMultiCategory $branch */
            $branch = static::model(['title' => "branch $name"]);
            $branch->appendTo($root->refresh())->save();

            /** @var ArchivedMultiCategory $leaf */
            $leaf = static::model(['title' => "leaf $name"]);
            $leaf->appendTo($branch->refresh())->save();

            /** @var ArchivedMultiCategory $aside */
            $aside = static::model(['title' => "aside $name"]);
            $aside->appendTo($root->refresh())->save();

            $nodes["root_$name"]   = $root;
            $nodes["branch_$name"] = $branch;
            $nodes["leaf_$name"]   = $leaf;
            $nodes["aside_$name"]  = $aside;
        }

        foreach ($nodes as $key => $node) {
            $nodes[$key] = $node->refresh();
        }

        return $nodes;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function bounds(ArchivedMultiCategory $node): array
    {
        $fresh = ArchivedMultiCategory::withTrashed()->whereKey($node->getKey())->first();

        return [
            $fresh->leftValue(),
            $fresh->rightValue(),
        ];
    }

    /**
     * Guards the fixture. Without identical numbering the tests below would pass whether or not
     * the tree condition is applied.
     */
    #[Test]
    public function theTwoTreesShareTheirBounds(): void
    {
        $nodes = $this->buildTrees();

        foreach (['root', 'branch', 'leaf', 'aside'] as $name) {
            static::assertSame(
                $this->bounds($nodes["{$name}_one"]),
                $this->bounds($nodes["{$name}_two"]),
                $name
            );
        }

        static::assertNotSame(
            $nodes['root_one']->treeValue(),
            $nodes['root_two']->treeValue()
        );
    }

    /**
     * Backs docs/Limitations.md: a trashed node keeps its place. Nothing shifts at all, in
     * either tree — the bookkeeping that closes the gap runs only for a hard delete.
     */
    #[Test]
    public function aTrashedNodeKeepsItsPlace(): void
    {
        $nodes = $this->buildTrees();

        $before = [];
        foreach ($nodes as $key => $node) {
            $before[$key] = $this->bounds($node);
        }

        $nodes['leaf_one']->delete();

        static::assertTrue(
            ArchivedMultiCategory::withTrashed()->whereKey($nodes['leaf_one']->getKey())->first()->trashed()
        );

        foreach ($nodes as $key => $node) {
            static::assertSame($before[$key], $this->bounds($node), $key);
        }
    }

    /**
     * The shift a hard delete performs has to reach the trashed rows of its own tree and stop
     * there. `aside` is trashed in both trees and carries the same bounds in each.
     */
    #[Test]
    public function theShiftCarriesTrashedNodesOfItsOwnTreeOnly(): void
    {
        $nodes = $this->buildTrees();

        $nodes['aside_one']->delete();
        $nodes['aside_two']->delete();

        static::assertSame([6, 7], $this->bounds($nodes['aside_one']));
        static::assertSame([6, 7], $this->bounds($nodes['aside_two']));

        // Removing leaf(3..4) for good closes a gap of two before `aside`.
        $nodes['leaf_one']->forceDelete();

        static::assertSame([4, 5], $this->bounds($nodes['aside_one']));
        static::assertSame([6, 7], $this->bounds($nodes['aside_two']));
    }

    #[Test]
    public function aHardDeleteLeavesTheOtherTreeAlone(): void
    {
        $nodes = $this->buildTrees();

        $nodes['leaf_one']->forceDelete();

        static::assertSame([1, 6], $this->bounds($nodes['root_one']));
        static::assertSame([1, 8], $this->bounds($nodes['root_two']));

        static::assertFalse((new HealthyChecker(ArchivedMultiCategory::class))->isBroken());
    }

    #[Test]
    public function deleteWithChildrenStaysInsideItsTree(): void
    {
        $nodes = $this->buildTrees();

        $nodes['branch_one']->deleteWithChildren(false);

        static::assertSame(
            [
                'root one',
                'aside one',
                'root two',
                'branch two',
                'leaf two',
                'aside two',
            ],
            ArchivedMultiCategory::query()->orderBy('tree_id')->orderBy('lft')->get()->pluck('title')->all()
        );

        // Soft, so nothing moved anywhere.
        static::assertSame([1, 8], $this->bounds($nodes['root_one']));
        static::assertSame([1, 8], $this->bounds($nodes['root_two']));
    }

    #[Test]
    public function forcingDeleteWithChildrenStaysInsideItsTree(): void
    {
        $nodes = $this->buildTrees();

        $nodes['branch_one']->deleteWithChildren();

        static::assertSame([1, 4], $this->bounds($nodes['root_one']));
        static::assertSame([1, 8], $this->bounds($nodes['root_two']));

        static::assertFalse((new HealthyChecker(ArchivedMultiCategory::class))->isBroken());
    }

    #[Test]
    public function relationsSkipTrashedNodesAndStayInTheirTree(): void
    {
        $nodes = $this->buildTrees();

        $nodes['leaf_one']->delete();

        static::assertSame(
            [
                'branch one',
                'aside one',
            ],
            $nodes['root_one']->refresh()->descendants()->get()->pluck('title')->all()
        );

        // The other tree is untouched, and its chain still reads root first.
        static::assertSame(
            [
                'root two',
                'branch two',
            ],
            $nodes['leaf_two']->refresh()->ancestors()->get()->pluck('title')->all()
        );

        // A trashed ancestor drops out of the chain rather than the chain ending at it.
        $nodes['branch_one']->refresh()->delete();

        static::assertSame(
            ['root one'],
            $nodes['leaf_one']->refresh()->ancestors()->get()->pluck('title')->all()
        );
    }

    /**
     * A subtree moved to another tree takes its trashed nodes with it: they are inside its
     * bounds, and leaving them behind would strand a row in a range that no longer holds it.
     */
    #[Test]
    public function movingASubtreeCarriesItsTrashedNodes(): void
    {
        $nodes = $this->buildTrees();

        $nodes['leaf_one']->delete();

        $nodes['branch_one']->refresh()->appendTo($nodes['root_two']->refresh())->save();

        $movedLeaf = ArchivedMultiCategory::withTrashed()->whereKey($nodes['leaf_one']->getKey())->first();

        static::assertTrue($movedLeaf->trashed());
        static::assertSame(
            $nodes['root_two']->refresh()->treeValue(),
            $movedLeaf->treeValue()
        );
        static::assertSame(
            $nodes['branch_one']->refresh()->treeValue(),
            $movedLeaf->treeValue()
        );

        static::assertFalse((new HealthyChecker(ArchivedMultiCategory::class))->isBroken());
    }
}
