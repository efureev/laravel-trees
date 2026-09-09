<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * `getNodeBounds()` answers with a positional array and builds it two different ways — from the
 * model's own attributes, or from a row fetched by id. Everything downstream reads it by index,
 * so the two have to agree on the order as well as the values.
 */
class NodeBoundsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> branch -> leaf
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
        ];
    }

    /**
     * The invariant the whole positional contract rests on, and the one nothing checked.
     */
    #[Test]
    public function bothBranchesOfGetNodeBoundsAgree(): void
    {
        $nodes = $this->buildTree();

        foreach ($nodes as $name => $node) {
            static::assertSame(
                $node->getNodeBounds($node),
                $node->getNodeBounds($node->getKey()),
                $name
            );
        }
    }

    /**
     * Asserted through the named accessors rather than through `columnsNames()`, so a change to
     * the configured order shows up here instead of cancelling itself out.
     */
    #[Test]
    public function theArrayIsInConfiguredColumnOrder(): void
    {
        $nodes = $this->buildTree();
        $leaf  = $nodes['leaf'];

        $expected = [
            $leaf->leftValue(),
            $leaf->rightValue(),
            $leaf->levelValue(),
            $leaf->parentValue(),
        ];

        static::assertSame($expected, $leaf->getNodeBounds($leaf));
        static::assertSame($expected, $leaf->getNodeBounds($leaf->getKey()));
    }

    #[Test]
    public function getPlainNodeDataOnAMissingIdStaysEmpty(): void
    {
        $this->buildTree();

        static::assertSame([], static::model()->newNestedSetQuery()->getPlainNodeData(999999));
    }

    /**
     * `whereDescendantOf()` accepts an id as well as a model, and the id form is the only thing
     * that reaches `getPlainNodeData()`. Nothing in the package passes an id, so without this
     * the branch runs nowhere.
     */
    #[Test]
    public function whereDescendantOfTakesAnIdAsWellAsAModel(): void
    {
        $nodes = $this->buildTree();
        $root  = $nodes['root'];

        $byModel = Category::query()->whereDescendantOf($root)->get()->pluck('title')->all();
        $byId    = Category::query()->whereDescendantOf($root->getKey())->get()->pluck('title')->all();

        static::assertSame(['branch', 'leaf'], $byModel);
        static::assertSame($byModel, $byId);
    }

    #[Test]
    public function whereDescendantOfCanIncludeTheNodeItself(): void
    {
        $nodes = $this->buildTree();

        $titles = Category::query()
            ->whereDescendantOf($nodes['branch']->getKey(), andSelf: true)
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(['branch', 'leaf'], $titles);
    }
}
