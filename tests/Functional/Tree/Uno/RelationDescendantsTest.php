<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Use cases and invariants of {@see \Fureev\Trees\Relations\DescendantsRelation}.
 */
class RelationDescendantsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root
     *   - node21
     *   - node31
     *     - node32
     *       - node321
     *   - node41
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];
        foreach (['node21', 'node31', 'node41'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root)->save();
            $nodes[$title] = $node;
        }

        /** @var Category $node32 */
        $node32 = static::model(['title' => 'node32']);
        $node32->appendTo($nodes['node31'])->save();
        $nodes['node32'] = $node32;

        /** @var Category $node321 */
        $node321 = static::model(['title' => 'node321']);
        $node321->appendTo($node32)->save();
        $nodes['node321'] = $node321;

        foreach ($nodes as $node) {
            $node->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function descendantsCollectsWholeSubtree(): void
    {
        $nodes = $this->buildTree();

        static::assertEquals(5, $nodes['root']->descendants()->count());
        static::assertEquals(2, $nodes['node31']->descendants()->count());
        static::assertEquals(1, $nodes['node32']->descendants()->count());
    }

    #[Test]
    public function descendantsReachEveryLevelNotOnlyChildren(): void
    {
        $nodes = $this->buildTree();

        $titles = $nodes['node31']->descendants()->get()->pluck('title')->all();

        // node321 sits two levels below node31 and must still be there.
        static::assertEqualsCanonicalizing(['node32', 'node321'], $titles);
    }

    #[Test]
    public function descendantsExcludeSelf(): void
    {
        $nodes = $this->buildTree();

        $keys = $nodes['node31']->descendants()->get()->modelKeys();

        static::assertNotContains($nodes['node31']->getKey(), $keys);
    }

    #[Test]
    public function descendantsOfLeafAreEmpty(): void
    {
        $nodes = $this->buildTree();

        static::assertTrue($nodes['node321']->isLeaf());
        static::assertCount(0, $nodes['node321']->descendants()->get());
    }

    #[Test]
    public function propertyAccessReturnsTheSameNodesAsTheQuery(): void
    {
        $nodes = $this->buildTree();

        /** @var Category $root */
        $root = Category::query()->find($nodes['root']->getKey());

        static::assertFalse($root->relationLoaded('descendants'));

        // Property access goes through BaseRelation::getResults().
        $viaProperty = $root->descendants;

        static::assertInstanceOf(Collection::class, $viaProperty);
        static::assertTrue($root->relationLoaded('descendants'));
        static::assertEqualsCanonicalizing(
            $root->descendants()->get()->modelKeys(),
            $viaProperty->modelKeys()
        );
    }

    #[Test]
    public function descendantsMatchTheQueryBuilderCounterpart(): void
    {
        $nodes = $this->buildTree();

        $viaRelation = $nodes['root']->descendants()->get()->modelKeys();
        $viaQuery    = $nodes['root']->newNestedSetQuery()->descendantsQuery()->get()->modelKeys();

        static::assertEqualsCanonicalizing($viaQuery, $viaRelation);
    }

    #[Test]
    public function descendantsFollowTheTreeAfterASubtreeMoves(): void
    {
        $nodes = $this->buildTree();

        static::assertEquals(2, $nodes['node31']->descendants()->count());
        static::assertEquals(0, $nodes['node21']->descendants()->count());

        // Relocate the whole node32 subtree under node21.
        $nodes['node32']->appendTo($nodes['node21']->refresh())->save();

        static::assertEquals(0, $nodes['node31']->refresh()->descendants()->count());
        static::assertEquals(2, $nodes['node21']->refresh()->descendants()->count());
    }
}
