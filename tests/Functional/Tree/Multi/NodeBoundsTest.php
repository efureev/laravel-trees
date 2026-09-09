<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * On a multi-tree model the bounds array carries the tree value, and `whereNodeBetween()` takes
 * it as the last element. That position is a contract, not a coincidence.
 */
class NodeBoundsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * Two trees, each root -> child -> leaf.
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

            /** @var MultiCategory $leaf */
            $leaf = static::model(['title' => "leaf $name"]);
            $leaf->appendTo($child->refresh())->save();

            $nodes["root_$name"]  = $root->refresh();
            $nodes["child_$name"] = $child->refresh();
            $nodes["leaf_$name"]  = $leaf->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function theTreeValueComesLast(): void
    {
        $nodes = $this->buildTrees();
        $leaf  = $nodes['leaf_two'];

        $bounds = $leaf->getNodeBounds($leaf);

        static::assertCount(5, $bounds);
        static::assertSame(
            [
                $leaf->leftValue(),
                $leaf->rightValue(),
                $leaf->levelValue(),
                $leaf->parentValue(),
                $leaf->treeValue(),
            ],
            $bounds
        );
        static::assertSame($leaf->treeValue(), end($bounds));
    }

    #[Test]
    public function bothBranchesOfGetNodeBoundsAgree(): void
    {
        $nodes = $this->buildTrees();

        foreach ($nodes as $name => $node) {
            static::assertSame(
                $node->getNodeBounds($node),
                $node->getNodeBounds($node->getKey()),
                $name
            );
        }
    }

    /**
     * The tree value has to survive the trip through the row, or the query would reach into the
     * other tree — whose bounds are numbered identically.
     */
    #[Test]
    public function whereDescendantOfByIdStaysInsideItsTree(): void
    {
        $nodes = $this->buildTrees();

        $titles = MultiCategory::query()
            ->whereDescendantOf($nodes['root_two']->getKey())
            ->defaultOrder()
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(
            [
                'child two',
                'leaf two',
            ],
            $titles
        );
    }

    /**
     * `whereNodeBetween()` reads the tree value off the end of the array. Handed only a pair of
     * bounds it used to take the right bound for a tree id and filter by nonsense.
     */
    #[Test]
    public function whereNodeBetweenRefusesAnArrayTooShortToCarryATree(): void
    {
        $this->buildTrees();

        $this->expectException(Exception::class);

        MultiCategory::query()->whereNodeBetween([1, 6])->get();
    }
}
