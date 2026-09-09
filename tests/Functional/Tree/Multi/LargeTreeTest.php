<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Concerns\SeedsLargeTrees;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Ten trees of a hundred thousand nodes each — a million rows in one table, which is the shape
 * the tree column exists for. Every tree numbers its bounds from 1, so all ten hold the same
 * numbers, and a shift that forgot its tree condition would rewrite nine hundred thousand rows
 * that have nothing to do with it.
 *
 * Seeding takes about twelve seconds, so the assertions are grouped into one test rather than
 * paying for the fixture again per case.
 */
class LargeTreeTest extends AbstractFunctionalTreeTestCase
{
    use SeedsLargeTrees;

    private const TREES = 10;

    private const PER_TREE = 100000;

    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * The outermost bound of every tree, keyed by tree.
     *
     * @return array<int|string, int>
     */
    private function outerBounds(): array
    {
        $rows = $this->connection()
            ->table($this->tableName())
            ->selectRaw('"tree_id", max("rgt") as m')
            ->groupBy('tree_id')
            ->get();

        $bounds = [];
        foreach ($rows as $row) {
            $bounds[$row->tree_id] = (int)$row->m;
        }

        ksort($bounds);

        return $bounds;
    }

    #[Test]
    public function aMillionNodesAcrossTenTrees(): void
    {
        for ($tree = 1; $tree <= self::TREES; $tree++) {
            $this->seedWideTree((self::PER_TREE - 1), $tree);
        }

        $this->linkChildrenToTheirRoots(perTree: true);

        static::assertSame((self::TREES * self::PER_TREE), MultiCategory::query()->count());
        static::assertSame(self::PER_TREE, MultiCategory::query()->byTree(7)->count());

        $this->assertTreeIsStructurallySound();

        // All ten hold identical numbers, which is what makes the isolation below meaningful.
        $before = $this->outerBounds();

        static::assertCount(self::TREES, $before);
        static::assertSame(1, count(array_unique($before)));

        /** @var MultiCategory $root */
        $root = MultiCategory::query()->where('lvl', 0)->byTree(4)->first();

        $connection = $this->connection();
        $connection->flushQueryLog();

        /** @var MultiCategory $node */
        $node = static::model(['title' => 'fresh']);
        $node->prependTo($root)->save();

        // Three round trips: read the target, make room, write the row. The statement that
        // makes room rewrites a hundred thousand rows, and it is still one statement.
        static::assertCount(3, $connection->getQueryLog());

        $after = $this->outerBounds();

        static::assertSame(($before[4] + 2), $after[4]);

        foreach ($before as $tree => $bound) {
            if ($tree === 4) {
                continue;
            }

            static::assertSame($bound, $after[$tree], "tree $tree should not have moved");
        }

        static::assertSame([2, 3], [$node->refresh()->leftValue(), $node->rightValue()]);
        static::assertSame(4, $node->treeValue());

        $this->assertTreeIsStructurallySound();
    }
}
