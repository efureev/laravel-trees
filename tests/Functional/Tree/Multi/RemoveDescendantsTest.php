<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Closing the vacated span is a bound shift, and every tree numbers its bounds from 1 — so the
 * shift has to stop at its own tree.
 */
class RemoveDescendantsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * Two trees of the same shape: root(1..8) -> a(2..5) -> a1(3..4), b(6..7)
     *
     * @return array<string, MultiCategory>
     */
    private function buildTrees(): array
    {
        $nodes = [];

        foreach (['one', 'two'] as $name) {
            /** @var MultiCategory $root */
            $root = static::model(['title' => "root $name"]);
            $root->save();

            /** @var MultiCategory $a */
            $a = static::model(['title' => "a $name"]);
            $a->appendTo($root->refresh())->save();

            /** @var MultiCategory $a1 */
            $a1 = static::model(['title' => "a1 $name"]);
            $a1->appendTo($a->refresh())->save();

            /** @var MultiCategory $b */
            $b = static::model(['title' => "b $name"]);
            $b->appendTo($root->refresh())->save();

            $nodes["root_$name"] = $root->refresh();
            $nodes["a_$name"]    = $a->refresh();
            $nodes["b_$name"]    = $b->refresh();
        }

        return $nodes;
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    private function boundsOfTree(int|string $tree): array
    {
        return MultiCategory::query()
            ->byTree($tree)
            ->defaultOrder()
            ->get()
            ->mapWithKeys(
                static fn(MultiCategory $n) => [
                    $n->title => [
                        $n->leftValue(),
                        $n->rightValue(),
                    ],
                ]
            )
            ->all();
    }

    #[Test]
    public function removingFromOneTreeLeavesTheOthersAlone(): void
    {
        $nodes = $this->buildTrees();

        $treeOne = $nodes['root_one']->treeValue();
        $treeTwo = $nodes['root_two']->treeValue();

        $before = $this->boundsOfTree($treeTwo);

        $nodes['a_one']->removeDescendants();

        static::assertSame(
            [
                'root one' => [
                    1,
                    6,
                ],
                'a one'    => [
                    2,
                    3,
                ],
                'b one'    => [
                    4,
                    5,
                ],
            ],
            $this->boundsOfTree($treeOne)
        );

        static::assertSame($before, $this->boundsOfTree($treeTwo));
        static::assertFalse((new HealthyChecker(MultiCategory::class))->isBroken());
    }
}
