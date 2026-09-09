<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\Concerns\SeedsLargeTrees;
use Fureev\Trees\Table;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Every other fixture in the suite is a handful of nodes, so nothing here had ever been asked
 * of a tree big enough for the bound shift to rewrite thousands of rows, or deep enough for the
 * traversals that recurse per level to run out of stack.
 *
 * Rows are written straight into the table with the bounds worked out by hand. Building the
 * same shape through the API would be quadratic — every insert shifts everything after it — and
 * would be testing the API rather than setting up for it.
 *
 * Nothing here asserts a duration. What is asserted is the statement count, which is the part
 * of cost that must not grow with the tree, and which does not vary between runs.
 */
class LargeTreeTest extends AbstractFunctionalTreeTestCase
{
    use SeedsLargeTrees;

    private const WIDE = 100000;

    /**
     * The size used where the test materialises every node as a model. Hydration costs around
     * 2.6 KB a node, so a hundred thousand of them needs some 300 MB — a limit of PHP's memory
     * setting rather than of the package, and not something to impose on a test run.
     */
    private const HYDRATED = 10000;

    private const DEEP = 1000;

    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    private function countStatements(callable $operation): int
    {
        $connection = static::model()->getConnection();

        $connection->flushQueryLog();
        $operation();

        return count($connection->getQueryLog());
    }

    /**
     * Builds three nodes through the API, the way an application would.
     *
     * @return array<string, Category>
     */
    private function seedSmall(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['a', 'b'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root->refresh())->save();
            $nodes[$title] = $node->refresh();
        }

        $nodes['root'] = $root->refresh();

        return $nodes;
    }

    /**
     * The shift rewrites the bounds of everything positioned after the new node, but it does so
     * in one statement. Insert costs the same number of round trips whether it moves two rows or
     * two thousand.
     */
    #[Test]
    public function insertingCostsTheSameStatementsAtAnySize(): void
    {
        $small = $this->seedSmall();

        $onSmall = $this->countStatements(
            static function () use ($small): void {
                /** @var Category $node */
                $node = static::model(['title' => 'fresh']);
                $node->prependTo($small['root'])->save();
            }
        );

        $this->truncateTable();
        $this->seedWideTree(self::WIDE);
        $this->linkChildrenToTheirRoots();

        /** @var Category $bigRoot */
        $bigRoot = Category::query()->where('lvl', 0)->first();

        $onLarge = $this->countStatements(
            static function () use ($bigRoot): void {
                /** @var Category $node */
                $node = static::model(['title' => 'fresh']);
                $node->prependTo($bigRoot)->save();
            }
        );

        static::assertSame($onSmall, $onLarge);
        static::assertSame((self::WIDE + 2), Category::query()->count());
        $this->assertTreeIsStructurallySound();
    }

    #[Test]
    public function movingCostsTheSameStatementsAtAnySize(): void
    {
        $small = $this->seedSmall();

        $onSmall = $this->countStatements(
            static function () use ($small): void {
                $small['a']->insertAfter($small['b'])->save();
            }
        );

        $this->truncateTable();
        $this->seedWideTree(self::WIDE);
        $this->linkChildrenToTheirRoots();

        /** @var Category $mid */
        $mid = Category::query()->where('title', 'c1000')->first();
        /** @var Category $last */
        $last = Category::query()->where('title', 'c' . self::WIDE)->first();

        $onLarge = $this->countStatements(
            static function () use ($mid, $last): void {
                $mid->insertAfter($last)->save();
            }
        );

        static::assertSame($onSmall, $onLarge);
        $this->assertTreeIsStructurallySound();
    }

    #[Test]
    public function deletingCostsTheSameStatementsAtAnySize(): void
    {
        $small = $this->seedSmall();

        $onSmall = $this->countStatements(
            static function () use ($small): void {
                $small['b']->delete();
            }
        );

        $this->truncateTable();
        $this->seedWideTree(self::WIDE);
        $this->linkChildrenToTheirRoots();

        /** @var Category $victim */
        $victim = Category::query()->where('title', 'c1500')->first();

        $onLarge = $this->countStatements(
            static function () use ($victim): void {
                $victim->delete();
            }
        );

        static::assertSame($onSmall, $onLarge);
        static::assertSame(self::WIDE, Category::query()->count());
        $this->assertTreeIsStructurallySound();
    }

    /**
     * The shift has to reach every row after the insertion point, not the first page of them.
     */
    #[Test]
    public function theShiftReachesTheFarEndOfALargeTree(): void
    {
        $this->seedWideTree(self::WIDE);
        $this->linkChildrenToTheirRoots();

        /** @var Category $root */
        $root = Category::query()->where('lvl', 0)->first();
        /** @var Category $last */
        $last = Category::query()->where('title', 'c' . self::WIDE)->first();

        $boundsBefore = [
            $last->leftValue(),
            $last->rightValue(),
        ];

        /** @var Category $node */
        $node = static::model(['title' => 'fresh']);
        $node->prependTo($root)->save();

        $last = $last->refresh();

        static::assertSame(
            [
                ($boundsBefore[0] + 2),
                ($boundsBefore[1] + 2),
            ],
            [
                $last->leftValue(),
                $last->rightValue(),
            ]
        );
        static::assertSame([2, 3], [$node->refresh()->leftValue(), $node->rightValue()]);
    }

    /**
     * `Table::draw()` and `Fixing::reorderNodes()` walk the tree by recursing once per level,
     * so depth is what puts them at risk rather than breadth. A hundred levels is far more than
     * any real hierarchy and costs nothing to check; the recursion only becomes a memory
     * problem in the thousands.
     */
    #[Test]
    public function aDeepChainIsWalkedWithoutRunningOutOfStack(): void
    {
        $this->seedDeepChain(self::DEEP);

        $this->assertTreeIsStructurallySound();

        /** @var Category $deepest */
        $deepest = Category::query()->orderByDesc('lvl')->first();

        static::assertSame((self::DEEP - 1), $deepest->levelValue());
        static::assertSame((self::DEEP - 1), $deepest->ancestors()->count());

        $tree = Category::query()->defaultOrder()->get()->toTree();

        static::assertCount(1, $tree);

        Table::fromTree(Category::query()->defaultOrder()->get()->toTree())->draw(new NullOutput());
    }

    /**
     * `toTree()` links the whole collection in memory, so its cost in statements is the one
     * query that fetched the rows — however many there are.
     */
    #[Test]
    public function toTreeOnThousandsOfNodesIsStillOneQuery(): void
    {
        $this->seedWideTree(self::HYDRATED);
        $this->linkChildrenToTheirRoots();

        $statements = $this->countStatements(
            static function (): void {
                Category::query()->defaultOrder()->get()->toTree();
            }
        );

        static::assertSame(1, $statements);

        $tree = Category::query()->defaultOrder()->get()->toTree();

        static::assertCount(1, $tree);
        static::assertCount(self::HYDRATED, $tree->first()->children);
    }
}
