<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Concerns;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Healthy\MissingParentCheck;
use Illuminate\Database\ConnectionInterface;

/**
 * Building a tree of this size through the API would be quadratic — every insert shifts
 * everything after it — so the rows go in with their bounds worked out by hand. What is under
 * test is what the package does *to* a large tree, not how long it takes to build one.
 */
trait SeedsLargeTrees
{
    private function connection(): ConnectionInterface
    {
        return static::model()->getConnection();
    }

    private function tableName(): string
    {
        return static::model()->getTable();
    }

    /**
     * TRUNCATE, not DELETE: the identity sequence has to go back to the start as well, or a
     * fixture seeded afterwards gets ids continuing from the previous one.
     */
    private function truncateTable(): void
    {
        $this->connection()->statement(
            'truncate table "' . $this->tableName() . '" restart identity cascade'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function insertRows(array $rows): void
    {
        $this->connection()->table($this->tableName())->insert($rows);
    }

    /**
     * One root with `$leaves` children beneath it: root(1 .. 2N+2), child i at (2i, 2i+1).
     *
     * The query log has to be off while this runs — the framework keeps every statement with
     * its bindings, and a million rows of them do not fit in memory.
     */
    private function seedWideTree(int $leaves, int|string|null $tree = null): void
    {
        $conn    = $this->connection();
        $logging = $conn->logging();
        $conn->disableQueryLog();

        $extra = $tree === null ? [] : ['tree_id' => $tree];
        $rows  = [
            ([
                'lft'    => 1,
                'rgt'    => (2 * $leaves + 2),
                'lvl'    => 0,
                'title'  => 'root' . ($tree === null ? '' : " $tree"),
                'params' => '{}',
            ] + $extra),
        ];

        for ($i = 1; $i <= $leaves; $i++) {
            $rows[] = ([
                'lft'    => (2 * $i),
                'rgt'    => (2 * $i + 1),
                'lvl'    => 1,
                'title'  => ($tree === null ? '' : "t$tree-") . "c$i",
                'params' => '{}',
            ] + $extra);

            if (count($rows) >= 5000) {
                $this->insertRows($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->insertRows($rows);
        }

        if ($logging) {
            $conn->enableQueryLog();
        }
    }

    /**
     * A single chain `$levels` deep: node i at (i+1, 2N-i), level i.
     */
    private function seedDeepChain(int $levels): void
    {
        $rows = [];

        for ($i = 0; $i < $levels; $i++) {
            $rows[] = [
                'lft'    => ($i + 1),
                'rgt'    => (2 * $levels - $i),
                'lvl'    => $i,
                'title'  => "d$i",
                'params' => '{}',
            ];
        }

        $this->insertRows($rows);

        // One node per level, so each one's parent is simply the node a level above it.
        $table = $this->tableName();
        $this->connection()->statement(
            "update \"$table\" c set \"parent_id\" = p.\"id\" from \"$table\" p"
            . ' where p."lvl" = c."lvl" - 1'
        );

        $this->assertFixtureHasNoOrphans();
    }

    /**
     * Points every level-1 node at the root of its own tree. Wired up afterwards rather than
     * guessed: the ids come from a sequence a previous fixture may already have advanced.
     */
    private function linkChildrenToTheirRoots(bool $perTree = false): void
    {
        $table = $this->tableName();

        $this->connection()->statement(
            $perTree
                ? "update \"$table\" c set \"parent_id\" = p.\"id\" from \"$table\" p"
                    . ' where p."lvl" = 0 and p."tree_id" = c."tree_id" and c."lvl" = 1'
                : "update \"$table\" set \"parent_id\" = (select \"id\" from \"$table\" where \"lvl\" = 0)"
                    . ' where "lvl" = 1'
        );

        $this->assertFixtureHasNoOrphans();
    }

    /**
     * `HealthyChecker` leaves the missing-parent check out of its list, so a fixture pointing at
     * an id that is not there would pass every other assertion unnoticed.
     */
    private function assertFixtureHasNoOrphans(): void
    {
        static::assertSame(0, (new MissingParentCheck(static::model()))->check());
    }

    /**
     * `HealthyChecker` on the whole fixture.
     *
     * These tests originally hand-rolled the same invariants as aggregate queries, because the
     * checker cross joined the table with itself and took twenty seconds at twenty thousand
     * rows. It groups and joins on equality now — 47 ms at a hundred thousand — so there is no
     * reason to keep a second implementation of the same questions.
     */
    private function assertTreeIsStructurallySound(): void
    {
        static::assertSame(
            [
                'OddnessCheck'       => 0,
                'DuplicatesCheck'    => 0,
                'WrongParentCheck'   => 0,
                'MissingParentCheck' => 0,
                'RangeCheck'         => 0,
                'RootCheck'          => 0,
            ],
            (new HealthyChecker(static::model()))->check()
        );
    }
}
