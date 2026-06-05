<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
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

    #[Test]
    public function fixTreeTurnsOrphanIntoRoot(): void
    {
        $root = $this->buildTree();

        /** @var FixableCategory $child */
        $child = FixableCategory::query()
            ->where((string)$root->parentAttribute(), $root->getKey())
            ->orderBy((string)$root->leftAttribute())
            ->first();

        // Point the child to a non-existent parent: it must become a root.
        $child->getConnection()
            ->table($child->getTable())
            ->where($child->getKeyName(), $child->getKey())
            ->update([(string)$root->parentAttribute() => 999999]);

        FixableCategory::fixTree();

        static::assertFalse((new HealthyChecker(FixableCategory::class))->isBroken());
    }
}
