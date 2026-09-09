<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every tree numbers its bounds from 1, so two trees hold the same numbers. What keeps
 * `isChildOf()` from pairing nodes across trees is the tree value it compares first.
 */
class IsChildOfTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * Two trees of the same shape: root -> child.
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

            /** @var MultiCategory $child */
            $child = static::model(['title' => "child $name"]);
            $child->appendTo($root->refresh())->save();

            $nodes["root_$name"]  = $root->refresh();
            $nodes["child_$name"] = $child->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function theTwoTreesReallyDoShareBounds(): void
    {
        $nodes = $this->buildTrees();

        static::assertSame($nodes['child_one']->leftValue(), $nodes['child_two']->leftValue());
        static::assertSame($nodes['child_one']->rightValue(), $nodes['child_two']->rightValue());
        static::assertNotSame($nodes['child_one']->treeValue(), $nodes['child_two']->treeValue());
    }

    #[Test]
    public function nodesInDifferentTreesAreNeverRelated(): void
    {
        $nodes = $this->buildTrees();

        static::assertTrue($nodes['child_one']->isDescendantOf($nodes['root_one']));

        // Identical bounds, different tree.
        static::assertFalse($nodes['child_one']->isChildOf($nodes['root_two']));
        static::assertFalse($nodes['child_one']->isDescendantOf($nodes['root_two']));
        static::assertFalse($nodes['child_one']->isDirectChildOf($nodes['root_two']));
    }

    #[Test]
    public function isDirectChildOfHoldsWithinATree(): void
    {
        $nodes = $this->buildTrees();

        static::assertTrue($nodes['child_two']->isDirectChildOf($nodes['root_two']));
        static::assertFalse($nodes['root_two']->isDirectChildOf($nodes['child_two']));
    }
}
