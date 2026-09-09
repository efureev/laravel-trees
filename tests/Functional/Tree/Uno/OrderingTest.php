<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * `defaultOrder()` replaces the ordering on the query, and four methods call it on the caller's
 * behalf. These pin down what survives that, what does not, and how to get your own ordering
 * back.
 *
 * The titles are deliberately out of alphabetical order relative to their position, so an
 * assertion on the order proves which key won rather than passing by coincidence.
 */
class OrderingTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> zulu, alpha, mike, in that position order.
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['zulu', 'alpha', 'mike'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root->refresh())->save();

            $nodes[$title] = $node->refresh();
        }

        $nodes['root'] = $root->refresh();

        return $nodes;
    }

    /**
     * A raw ordering carries bindings. Dropping the clause without dropping its bindings leaves
     * a value in the statement with no placeholder left to fill, and every later binding shifts
     * by one.
     */
    #[Test]
    public function defaultOrderDropsTheBindingsOfARawOrder(): void
    {
        $this->buildTree();

        $titles = Category::query()
            ->orderByRaw('case when title = ? then 0 else 1 end', ['mike'])
            ->defaultOrder()
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(['root', 'zulu', 'alpha', 'mike'], $titles);
    }

    /**
     * The same thing reached the way an application actually reaches it: `parents()` calls
     * `defaultOrder()` itself, so the caller never sees the reordering happen.
     */
    #[Test]
    public function anImplicitDefaultOrderDropsThemToo(): void
    {
        $nodes = $this->buildTree();

        $titles = $nodes['mike']
            ->newNestedSetQuery()
            ->orderByRaw('case when title = ? then 0 else 1 end', ['zulu'])
            ->parents()
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(['root'], $titles);
    }

    #[Test]
    public function defaultOrderReplacesAnEarlierOrder(): void
    {
        $this->buildTree();

        $titles = Category::query()
            ->orderBy('title')
            ->defaultOrder()
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(['root', 'zulu', 'alpha', 'mike'], $titles);
    }

    #[Test]
    public function childrenComeBackInPositionOrder(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(
            [
                'zulu',
                'alpha',
                'mike',
            ],
            $nodes['root']->children()->get()->pluck('title')->all()
        );
    }

    /**
     * The trap: the ordering is kept, but behind `lft`. Within one tree `lft` is unique, so the
     * tie it would break never happens and the ordering has no effect at all.
     */
    #[Test]
    public function anOrderAddedAfterChildrenNeverTakesEffect(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(
            [
                'zulu',
                'alpha',
                'mike',
            ],
            $nodes['root']->children()->orderBy('title')->get()->pluck('title')->all()
        );
    }

    #[Test]
    public function reorderMakesYourOwnOrderWin(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(
            [
                'alpha',
                'mike',
                'zulu',
            ],
            $nodes['root']->children()->reorder('title')->get()->pluck('title')->all()
        );
    }

    #[Test]
    public function parentsComeBackRootFirstWhateverYouAskedFor(): void
    {
        $nodes = $this->buildTree();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'aaa leaf']);
        $leaf->appendTo($nodes['zulu']->refresh())->save();

        $titles = $leaf->refresh()
            ->newNestedSetQuery()
            ->orderBy('title', 'desc')
            ->parents()
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(['root', 'zulu'], $titles);
    }
}
