<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * Inserting a node is several statements — read the target, shift the bounds to make room,
 * write the row — and the tree is not valid between them. `Readme.md` puts the responsibility
 * for that on the application: "It is highly recommended to use a database that supports
 * transactions (like PostgreSQL) to protect tree structures from corruption."
 *
 * PHPUnit runs on one thread, so the race is staged rather than raced: a second connection
 * writes from inside a `creating` listener, which puts it exactly in the window between the
 * shift and the insert. Deterministic, and the same failure a real second process would cause.
 */
class ConcurrencyTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    public function setUp(): void
    {
        parent::setUp();

        config(['database.connections.rival' => config('database.connections.pgsql')]);
    }

    protected function tearDown(): void
    {
        DB::purge('rival');

        parent::tearDown();
    }

    private function createRootNode(): Category
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        return $root->refresh();
    }

    /**
     * Registers a listener that inserts a competing node on a second connection the first time
     * any node is created, then returns what that attempt threw, if anything.
     */
    private function raceOn(Category $root, ?string $lockTimeout = null): callable
    {
        $outcome = new class {
            public ?Throwable $error = null;
            public bool $ran         = false;
        };

        Category::creating(
            static function () use ($root, $lockTimeout, $outcome) {
                if ($outcome->ran) {
                    return;
                }
                $outcome->ran = true;

                try {
                    if ($lockTimeout !== null) {
                        DB::connection('rival')->statement("SET lock_timeout = '$lockTimeout'");
                    }

                    /** @var Category $rivalRoot */
                    $rivalRoot = Category::on('rival')->whereKey($root->getKey())->first();

                    $rival = new Category(['title' => 'rival']);
                    $rival->setConnection('rival');
                    $rival->prependTo($rivalRoot)->save();
                } catch (Throwable $e) {
                    $outcome->error = $e;
                }
            }
        );

        return static fn(): ?Throwable => $outcome->error;
    }

    /**
     * The hazard itself. Both writers read the same bounds, both make room at the same place,
     * and both land on it.
     */
    #[Test]
    public function twoWritersWithoutATransactionCorruptTheTree(): void
    {
        $root  = $this->createRootNode();
        $error = $this->raceOn($root);

        /** @var Category $mine */
        $mine = static::model(['title' => 'mine']);
        $mine->prependTo($root)->save();

        static::assertNull($error(), 'the rival was expected to get through');

        $bounds = Category::query()
            ->whereIn('title', ['rival', 'mine'])
            ->get()
            ->map(static fn(Category $n) => [$n->leftValue(), $n->rightValue()])
            ->all();

        static::assertSame($bounds[0], $bounds[1], 'both nodes should have landed on the same bounds');
        static::assertTrue((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * And the remedy the readme names. The rival's own shift has to touch a row this
     * transaction already holds, so it waits — here only briefly, because the connection is
     * told to give up rather than hang the test.
     */
    #[Test]
    public function aTransactionKeepsTheSecondWriterOut(): void
    {
        $root  = $this->createRootNode();
        $error = $this->raceOn($root, '300ms');

        DB::connection()->transaction(
            static function () use ($root) {
                /** @var Category $mine */
                $mine = static::model(['title' => 'mine']);
                $mine->prependTo($root)->save();
            }
        );

        static::assertInstanceOf(QueryException::class, $error());
        static::assertStringContainsString('lock timeout', $error()->getMessage());

        static::assertSame(
            [
                'root',
                'mine',
            ],
            Category::query()->defaultOrder()->get()->pluck('title')->all()
        );
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * The in-process half of the same problem, which the package does defend against: a target
     * loaded before someone else moved it is re-read before the bounds are used.
     */
    #[Test]
    public function aStaleTargetIsRefreshedBeforeInserting(): void
    {
        $root = $this->createRootNode();

        /** @var Category $stale */
        $stale = Category::query()->whereKey($root->getKey())->first();

        /** @var Category $first */
        $first = static::model(['title' => 'first']);
        $first->appendTo($root->refresh())->save();

        // $stale still holds 1..2 in memory while the row is now 1..4.
        static::assertSame([1, 2], [$stale->leftValue(), $stale->rightValue()]);

        /** @var Category $second */
        $second = static::model(['title' => 'second']);
        $second->appendTo($stale)->save();

        static::assertSame(
            [
                'root',
                'first',
                'second',
            ],
            Category::query()->defaultOrder()->get()->pluck('title')->all()
        );
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }
}
