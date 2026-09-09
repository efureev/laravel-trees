<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\NotSupportedException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function root(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isEqualTo($node31->root()->first()));
        static::assertCount(1, $node31->root()->get());
        static::assertTrue($modelRoot->isEqualTo($node31->root()->get()->first()));
        static::assertTrue($modelRoot->isEqualTo($node31->getRoot()));

        static::assertTrue($modelRoot->isEqualTo(Category::root()->first()));
        static::assertCount(1, Category::root()->get());
        static::assertTrue($modelRoot->isEqualTo(Category::root()->get()->first()));
    }

    #[Test]
    public function notRoot(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $list = Category::notRoot()->get();

        static::assertCount(2, $list);
    }

    /**
     * root -> a -> a1 -> a2, plus a sibling branch off the root.
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

        foreach (['a', 'a1', 'a2'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($parent->refresh())->save();
            $nodes[$title] = $node->refresh();
            $parent        = $nodes[$title];
        }

        /** @var Category $aside */
        $aside = static::model(['title' => 'aside']);
        $aside->appendTo($root->refresh())->save();
        $nodes['aside'] = $aside->refresh();

        return $nodes;
    }

    #[Test]
    public function parentsByModelIdReturnsAncestors(): void
    {
        $nodes = $this->buildChain();

        $titles = Category::parentsByModelId($nodes['a2']->getKey())
            ->get()
            ->pluck('title')
            ->all();

        // Root first, the node itself excluded, the sibling branch left out.
        static::assertSame(
            [
                'root node',
                'a',
                'a1',
            ],
            $titles
        );
    }

    #[Test]
    public function parentsByModelIdCanIncludeSelf(): void
    {
        $nodes = $this->buildChain();

        $titles = Category::parentsByModelId($nodes['a2']->getKey(), andSelf: true)
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(
            [
                'root node',
                'a',
                'a1',
                'a2',
            ],
            $titles
        );
    }

    #[Test]
    public function parentsByModelIdCanBeLimitedByLevel(): void
    {
        $nodes = $this->buildChain();

        $titles = Category::parentsByModelId($nodes['a2']->getKey(), level: 1)
            ->get()
            ->pluck('title')
            ->all();

        // The root sits at level 0 and drops out.
        static::assertSame(
            [
                'a',
                'a1',
            ],
            $titles
        );
    }

    #[Test]
    public function parentsByModelIdOnAMissingIdReturnsNothing(): void
    {
        $this->buildChain();

        static::assertCount(0, Category::parentsByModelId(999999)->get());
    }

    /**
     * The whole point of the method is answering without loading the node first — one statement,
     * not a fetch followed by a query.
     */
    #[Test]
    public function parentsByModelIdIssuesASingleQuery(): void
    {
        $nodes = $this->buildChain();

        static::model()->getConnection()->flushQueryLog();

        Category::parentsByModelId($nodes['a2']->getKey())->get();

        static::assertCount(1, static::model()->getConnection()->getQueryLog());
    }
}
