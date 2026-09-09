<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\FixableMultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * `makeGap()` shifts bounds from a cut point onwards. On a multi-tree model it has to stay
 * inside the tree of the model it was reached through — every tree numbers from 1, so without
 * that condition it would move the others too.
 */
class MakeGapTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<FixableMultiCategory>
     */
    protected static function modelClass(): string
    {
        return FixableMultiCategory::class;
    }

    /**
     * Two trees of the same shape: root -> child.
     *
     * @return array<string, FixableMultiCategory>
     */
    private function buildTrees(): array
    {
        $nodes = [];

        foreach (['one', 'two'] as $name) {
            /** @var FixableMultiCategory $root */
            $root = static::model(['title' => "root $name"]);
            $root->save();

            /** @var FixableMultiCategory $child */
            $child = static::model(['title' => "child $name"]);
            $child->appendTo($root->refresh())->save();

            $nodes["root_$name"]  = $root->refresh();
            $nodes["child_$name"] = $child->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function theGapStaysInsideItsOwnTree(): void
    {
        $nodes = $this->buildTrees();

        $before = [
            $nodes['root_two']->leftValue(),
            $nodes['root_two']->rightValue(),
        ];

        $nodes['root_one']->newScopedQuery()->makeGap(1, 2);

        static::assertSame(
            [
                3,
                6,
            ],
            [
                $nodes['root_one']->refresh()->leftValue(),
                $nodes['root_one']->refresh()->rightValue(),
            ]
        );

        // The other tree carries the same numbers and must not have moved.
        static::assertSame(
            $before,
            [
                $nodes['root_two']->refresh()->leftValue(),
                $nodes['root_two']->refresh()->rightValue(),
            ]
        );
    }
}
