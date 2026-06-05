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
}
