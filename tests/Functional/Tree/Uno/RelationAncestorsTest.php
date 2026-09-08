<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Use cases and invariants of {@see \Fureev\Trees\Relations\AncestorsRelation}.
 */
class RelationAncestorsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> node2 -> node3 -> node4, plus a sibling branch off the root.
     *
     * @return array<string, Category>
     */
    private function buildChain(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        $nodes  = ['root' => $root];
        $parent = $root;

        foreach (['node2', 'node3', 'node4'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($parent)->save();
            $nodes[$title] = $node;
            $parent        = $node;
        }

        /** @var Category $aside */
        $aside = static::model(['title' => 'aside']);
        $aside->appendTo($root)->save();
        $nodes['aside'] = $aside;

        foreach ($nodes as $node) {
            $node->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function ancestorsCollectTheWholeChainUpToRoot(): void
    {
        $nodes = $this->buildChain();

        static::assertEquals(0, $nodes['root']->ancestors()->count());
        static::assertEquals(1, $nodes['node2']->ancestors()->count());
        static::assertEquals(2, $nodes['node3']->ancestors()->count());
        static::assertEquals(3, $nodes['node4']->ancestors()->count());
    }

    #[Test]
    public function ancestorsAreOrderedFromTheRootDown(): void
    {
        $nodes = $this->buildChain();

        $titles = $nodes['node4']->ancestors()->get()->pluck('title')->all();

        static::assertSame(['root node', 'node2', 'node3'], $titles);
    }

    #[Test]
    public function ancestorsExcludeSelfAndSiblingBranches(): void
    {
        $nodes = $this->buildChain();

        $keys = $nodes['node4']->ancestors()->get()->modelKeys();

        static::assertNotContains($nodes['node4']->getKey(), $keys);
        static::assertNotContains($nodes['aside']->getKey(), $keys);
    }

    #[Test]
    public function ancestorsOfRootAreEmpty(): void
    {
        $nodes = $this->buildChain();

        static::assertTrue($nodes['root']->isRoot());
        static::assertCount(0, $nodes['root']->ancestors()->get());
    }

    #[Test]
    public function propertyAccessReturnsTheSameNodesAsTheQuery(): void
    {
        $nodes = $this->buildChain();

        /** @var Category $node4 */
        $node4 = Category::query()->find($nodes['node4']->getKey());

        static::assertFalse($node4->relationLoaded('ancestors'));

        // Property access goes through BaseRelation::getResults().
        $viaProperty = $node4->ancestors;

        static::assertInstanceOf(Collection::class, $viaProperty);
        static::assertTrue($node4->relationLoaded('ancestors'));
        static::assertSame(
            $node4->ancestors()->get()->modelKeys(),
            $viaProperty->modelKeys()
        );
    }

    #[Test]
    public function ancestorsMatchTheParentsQuery(): void
    {
        $nodes = $this->buildChain();

        $viaRelation = $nodes['node4']->ancestors()->get()->modelKeys();
        $viaQuery    = $nodes['node4']->parents()->modelKeys();

        static::assertSame($viaQuery, $viaRelation);
    }

    #[Test]
    public function ancestorsFollowTheTreeAfterTheNodeMoves(): void
    {
        $nodes = $this->buildChain();

        static::assertEquals(3, $nodes['node4']->ancestors()->count());

        // Re-attach the node straight to the root: one ancestor is left.
        $nodes['node4']->appendTo($nodes['root']->refresh())->save();

        static::assertEquals(1, $nodes['node4']->refresh()->ancestors()->count());
    }
}
