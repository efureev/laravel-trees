<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\TreeBuilder;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers `has()` / `whereHas()` / `doesntHave()` / `withCount()` over the tree relations,
 * which all go through Relation::getRelationExistenceQuery().
 */
class RelationExistenceTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Root with 2 children, each holding 2 children: 7 nodes, 3 of them branches.
     */
    private function buildTree(): Category
    {
        return TreeBuilder::from(self::modelClass())->build(2, 2);
    }

    #[Test]
    public function hasDescendantsReturnsOnlyBranchNodes(): void
    {
        $this->buildTree();

        $branches = Category::query()->has('descendants')->get();

        static::assertCount(3, $branches);
        foreach ($branches as $node) {
            static::assertFalse($node->isLeaf(), "[{$node->title}] must not be a leaf");
        }
    }

    #[Test]
    public function hasAncestorsReturnsOnlyNonRoots(): void
    {
        $this->buildTree();

        $nested = Category::query()->has('ancestors')->get();

        static::assertCount(6, $nested);
        foreach ($nested as $node) {
            static::assertFalse($node->isRoot(), "[{$node->title}] must not be a root");
        }
    }

    #[Test]
    public function doesntHaveDescendantsReturnsLeaves(): void
    {
        $this->buildTree();

        $leaves = Category::query()->doesntHave('descendants')->get();

        static::assertCount(4, $leaves);
        foreach ($leaves as $node) {
            static::assertTrue($node->isLeaf(), "[{$node->title}] must be a leaf");
        }
    }

    #[Test]
    public function whereHasDescendantsAppliesCallback(): void
    {
        $root = $this->buildTree();

        /** @var Category $deepest */
        $deepest = Category::query()->orderByDesc((string)$root->levelAttribute())->first();

        $matched = Category::query()
            ->whereHas(
                'descendants',
                static function ($query) use ($deepest) {
                    $query->where('title', $deepest->title);
                }
            )
            ->get();

        // Only the ancestors of that single node contain it: root and its parent.
        static::assertCount(2, $matched);
        foreach ($matched as $node) {
            static::assertTrue($deepest->isChildOf($node));
        }
    }

    #[Test]
    public function withCountDescendants(): void
    {
        $root = $this->buildTree();

        /** @var Category $counted */
        $counted = Category::query()->withCount('descendants')->find($root->getKey());

        static::assertEquals(6, $counted->descendants_count);
    }
}
