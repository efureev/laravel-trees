<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\DuplicatesCheck;
use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Healthy\MissingParentCheck;
use Fureev\Trees\Healthy\OddnessCheck;
use Fureev\Trees\Healthy\RootCheck;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\TreeBuilder;
use Fureev\Trees\Tests\models\v5\FixableCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the tree-fixing helpers (category B): {@see \Fureev\Trees\QueryBuilder\Fixing}.
 *
 * Each test builds a valid tree, corrupts the nested-set boundaries directly in
 * the database (keeping the parent linkage intact) and asserts that fixTree()
 * rebuilds a healthy tree.
 */
class FixingTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<FixableCategory>
     */
    protected static function modelClass(): string
    {
        return FixableCategory::class;
    }

    private function buildTree(): FixableCategory
    {
        return TreeBuilder::from(self::modelClass())->build(2, 2);
    }

    /**
     * Shifts the lft/rgt boundaries of a node directly in the DB, bypassing
     * the nested-set bookkeeping, so the tree becomes inconsistent on purpose.
     */
    private function corruptBounds(FixableCategory $node, int $delta): void
    {
        $node->getConnection()
            ->table($node->getTable())
            ->where($node->getKeyName(), $node->getKey())
            ->update(
                [
                    (string)$node->leftAttribute()  => ($node->leftValue() + $delta),
                    (string)$node->rightAttribute() => ($node->rightValue() + $delta),
                ]
            );
    }

    #[Test]
    public function fixTreeRebuildsBrokenTree(): void
    {
        $root = $this->buildTree();

        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());

        // Corrupt every non-root node's boundaries.
        FixableCategory::query()
            ->whereNotNull((string)$root->parentAttribute())
            ->get()
            ->each(fn(FixableCategory $node) => $this->corruptBounds($node, 100));

        static::assertTrue((new HealthyChecker(FixableCategory::class))->isBroken());

        $changed = FixableCategory::fixTree();

        static::assertGreaterThan(0, $changed);
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }

    #[Test]
    public function fixTreeIsIdempotent(): void
    {
        $root = $this->buildTree();

        FixableCategory::query()
            ->whereNotNull((string)$root->parentAttribute())
            ->get()
            ->each(fn(FixableCategory $node) => $this->corruptBounds($node, 50));

        FixableCategory::fixTree();

        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());

        // A healthy tree needs no changes.
        static::assertSame(0, FixableCategory::fixTree());
    }

    #[Test]
    public function fixTreeOnHealthyTreeChangesNothing(): void
    {
        $this->buildTree();

        static::assertSame(0, FixableCategory::fixTree());
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }

    #[Test]
    public function fixSubTreeRebuildsBrokenSubTree(): void
    {
        $root = $this->buildTree();

        /** @var FixableCategory $branch */
        $branch = FixableCategory::query()
            ->where((string)$root->parentAttribute(), $root->getKey())
            ->orderBy((string)$root->leftAttribute())
            ->first();

        // Inject an extra child directly into the DB inside the branch range, but
        // without making room for it (no gap). This keeps it discoverable as a
        // descendant while leaving the tree inconsistent, so fixSubTree() has to
        // grow the branch and shift the rest of the tree via makeGap().
        $branch->getConnection()
            ->table($branch->getTable())
            ->insert(
                [
                    (string)$branch->leftAttribute()   => ($branch->leftValue() + 1),
                    (string)$branch->rightAttribute()  => ($branch->leftValue() + 2),
                    (string)$branch->levelAttribute()  => ($branch->levelValue() + 1),
                    (string)$branch->parentAttribute() => $branch->getKey(),
                    'title'                            => 'injected child',
                ]
            );

        static::assertTrue((new HealthyChecker(FixableCategory::class))->isBroken());

        $changed = FixableCategory::query()->fixSubTree($branch);

        static::assertGreaterThan(0, $changed);
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }

    #[Test]
    public function fixMultiTreeDelegatesToFixTreeForSingleTree(): void
    {
        $root = $this->buildTree();

        FixableCategory::query()
            ->whereNotNull((string)$root->parentAttribute())
            ->get()
            ->each(fn(FixableCategory $node) => $this->corruptBounds($node, 100));

        $result = FixableCategory::query()->fixMultiTree();

        static::assertIsArray($result);
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }

    /**
     * A node whose parent row is gone cannot be placed from its own link, and the bounds are
     * exactly what the repair is rebuilding, so it is hung from the root.
     *
     * It used to become a root itself, which leaves a single tree with two — a shape the package
     * refuses to write, and one no check could see until `RootCheck` existed.
     */
    #[Test]
    public function fixTreeAttachesAnOrphanToTheRoot(): void
    {
        $root = $this->buildTree();

        /** @var FixableCategory $child */
        $child = FixableCategory::query()
            ->where((string)$root->parentAttribute(), $root->getKey())
            ->orderBy((string)$root->leftAttribute())
            ->first();

        $child->getConnection()
            ->table($child->getTable())
            ->where($child->getKeyName(), $child->getKey())
            ->update([(string)$root->parentAttribute() => 999999]);

        FixableCategory::fixTree();

        $repaired = FixableCategory::query()->whereKey($child->getKey())->first();

        static::assertSame($root->getKey(), $repaired->parentValue());
        static::assertFalse($repaired->isRoot());

        static::assertSame(0, (new RootCheck(FixableCategory::class))->check());
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }

    /**
     * Parent links pointing at each other are reachable from nowhere, so the walk never sees
     * them. They are hung from the root for the same reason an orphan is.
     */
    #[Test]
    public function fixTreeBreaksACycle(): void
    {
        $root = $this->buildTree();

        $children = FixableCategory::query()
            ->where((string)$root->parentAttribute(), $root->getKey())
            ->orderBy((string)$root->leftAttribute())
            ->get();

        $first  = $children->first();
        $second = $children->last();

        static::assertTrue($first->isNot($second));

        $table = $first->getTable();

        $first->getConnection()->table($table)
            ->where($first->getKeyName(), $first->getKey())
            ->update([(string)$root->parentAttribute() => $second->getKey()]);

        $second->getConnection()->table($table)
            ->where($second->getKeyName(), $second->getKey())
            ->update([(string)$root->parentAttribute() => $first->getKey()]);

        FixableCategory::fixTree();

        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
        static::assertSame(
            1,
            FixableCategory::query()->whereNull((string)$root->parentAttribute())->count()
        );
    }

    /**
     * With every link dangling there is no root to hang anything from, so one of them becomes
     * it — and exactly one, not one per orphan.
     */
    #[Test]
    public function fixTreeSalvagesATreeWithoutARoot(): void
    {
        $root = $this->buildTree();

        FixableCategory::query()->getQuery()
            ->update([(string)$root->parentAttribute() => 999999]);

        FixableCategory::fixTree();

        static::assertSame(
            1,
            FixableCategory::query()->whereNull((string)$root->parentAttribute())->count()
        );
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }


    #[Test]
    public function makeGapWithZeroHeightLeavesBoundsUntouched(): void
    {
        $this->buildTree();

        $bounds = static fn(): array => FixableCategory::query()
            ->orderBy((string)FixableCategory::make()->leftAttribute())
            ->pluck(
                (string)FixableCategory::make()->rightAttribute(),
                (string)FixableCategory::make()->leftAttribute()
            )
            ->all();

        $before = $bounds();

        // A zero offset used to render as `"lft"0` and fail as a SQL syntax error
        // instead of behaving as the no-op it is.
        FixableCategory::query()->makeGap(1, 0);

        static::assertSame($before, $bounds());
        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }
}
