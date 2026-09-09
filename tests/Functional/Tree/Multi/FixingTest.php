<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\TreeBuilder;
use Fureev\Trees\Tests\models\v5\FixableMultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers {@see \Fureev\Trees\QueryBuilder\Fixing::fixMultiTree()} (category B).
 */
class FixingTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<FixableMultiCategory>
     */
    protected static function modelClass(): string
    {
        return FixableMultiCategory::class;
    }

    /**
     * Collapses a node onto another node's boundaries (a duplicate), staying
     * within the root range so the node remains discoverable as a descendant
     * while making the tree inconsistent on purpose.
     */
    private function collideBounds(FixableMultiCategory $node, int $lft, int $rgt): void
    {
        $node->getConnection()
            ->table($node->getTable())
            ->where($node->getKeyName(), $node->getKey())
            ->update(
                [
                    (string)$node->leftAttribute()  => $lft,
                    (string)$node->rightAttribute() => $rgt,
                ]
            );
    }

    #[Test]
    public function fixMultiTreeRebuildsEveryTree(): void
    {
        $rootA = TreeBuilder::from(self::modelClass(), 'root A')->build(2);
        $rootB = TreeBuilder::from(self::modelClass(), 'root B')->build(2);

        static::assertNotSame($rootA->tree_id, $rootB->tree_id);
        static::assertFalse((new HealthyChecker(FixableMultiCategory::class))->isBroken());

        // Corrupt the children of every tree by collapsing them onto the same
        // boundaries (duplicates) while keeping them inside the root range.
        FixableMultiCategory::query()
            ->whereNotNull((string)$rootA->parentAttribute())
            ->get()
            ->each(fn(FixableMultiCategory $node) => $this->collideBounds($node, 2, 3));

        static::assertTrue((new HealthyChecker(FixableMultiCategory::class))->isBroken());

        $result = FixableMultiCategory::query()->fixMultiTree();

        static::assertIsArray($result);
        static::assertArrayHasKey($rootA->tree_id, $result);
        static::assertArrayHasKey($rootB->tree_id, $result);

        static::assertFalse((new HealthyChecker(FixableMultiCategory::class))->isBroken());
    }

    #[Test]
    public function fixMultiTreeIsIdempotent(): void
    {
        TreeBuilder::from(self::modelClass(), 'root A')->build(2);
        TreeBuilder::from(self::modelClass(), 'root B')->build(2);

        $result = FixableMultiCategory::query()->fixMultiTree();

        static::assertFalse((new HealthyChecker(FixableMultiCategory::class))->isBroken());

        // Each healthy tree reports zero changed nodes.
        foreach ($result as $changed) {
            static::assertSame(0, $changed);
        }
    }

    /**
     * Repairing a multi tree used to leave it worse than it started. The orphan was dropped from
     * the walk instead of being placed, so it kept its old bounds while everything around it was
     * renumbered on top of them: one dangling link became a pile of colliding ones.
     */
    #[Test]
    public function fixMultiTreeAttachesAnOrphanToItsOwnRoot(): void
    {
        $nodes = [];

        foreach (['one', 'two'] as $name) {
            /** @var FixableMultiCategory $root */
            $root = static::model(['title' => "root $name"]);
            $root->save();

            /** @var FixableMultiCategory $branch */
            $branch = static::model(['title' => "branch $name"]);
            $branch->appendTo($root->refresh())->save();

            /** @var FixableMultiCategory $leaf */
            $leaf = static::model(['title' => "leaf $name"]);
            $leaf->appendTo($branch->refresh())->save();

            $nodes["root_$name"] = $root->refresh();
            $nodes["leaf_$name"] = $leaf->refresh();
        }

        $victim = $nodes['leaf_one'];

        $victim->getConnection()
            ->table($victim->getTable())
            ->where($victim->getKeyName(), $victim->getKey())
            ->update([(string)$victim->parentAttribute() => 999999]);

        $untouched = FixableMultiCategory::query()
            ->byTree($nodes['root_two']->treeValue())
            ->defaultOrder()
            ->get()
            ->map(static fn(FixableMultiCategory $n) => [$n->leftValue(), $n->rightValue()])
            ->all();

        FixableMultiCategory::fixMultiTree();

        $repaired = FixableMultiCategory::query()->whereKey($victim->getKey())->first();

        static::assertSame($nodes['root_one']->getKey(), $repaired->parentValue());
        static::assertSame($nodes['root_one']->treeValue(), $repaired->treeValue());

        static::assertFalse((new HealthyChecker(FixableMultiCategory::class))->isBroken());

        // The other tree is renumbered by its own pass and must come out unchanged.
        static::assertSame(
            $untouched,
            FixableMultiCategory::query()
                ->byTree($nodes['root_two']->treeValue())
                ->defaultOrder()
                ->get()
                ->map(static fn(FixableMultiCategory $n) => [$n->leftValue(), $n->rightValue()])
                ->all()
        );
    }
}
