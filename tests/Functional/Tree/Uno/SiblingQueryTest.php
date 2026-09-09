<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * `prevSiblings()` and `nextSiblings()` narrow `prevNodes()` / `nextNodes()` down to nodes
 * sharing a parent. The fixture puts a nephew on either side, so the narrowing has something to
 * remove — without them the two pairs would answer identically and prove nothing.
 */
class SiblingQueryTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> a (-> a1), b, c (-> c1)
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['a', 'b', 'c'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root->refresh())->save();
            $nodes[$title] = $node->refresh();
        }

        foreach (['a', 'c'] as $title) {
            /** @var Category $child */
            $child = static::model(['title' => $title . '1']);
            $child->appendTo($nodes[$title]->refresh())->save();
            $nodes[$title . '1'] = $child->refresh();
        }

        foreach ($nodes as $key => $node) {
            $nodes[$key] = $node->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function prevSiblingsDropTheNephews(): void
    {
        $nodes = $this->buildTree();

        $before   = $nodes['c']->newNestedSetQuery()->prevNodes()->defaultOrder()->get()->pluck('title')->all();
        $siblings = $nodes['c']->newNestedSetQuery()->prevSiblings()->defaultOrder()->get()->pluck('title')->all();

        // Everything positioned earlier, nephew included.
        static::assertSame(
            [
                'root',
                'a',
                'a1',
                'b',
            ],
            $before
        );

        static::assertSame(
            [
                'a',
                'b',
            ],
            $siblings
        );
    }

    #[Test]
    public function nextSiblingsDropTheNephews(): void
    {
        $nodes = $this->buildTree();

        $after    = $nodes['a']->newNestedSetQuery()->nextNodes()->defaultOrder()->get()->pluck('title')->all();
        $siblings = $nodes['a']->newNestedSetQuery()->nextSiblings()->defaultOrder()->get()->pluck('title')->all();

        // nextNodes() also carries the node's own descendants — a1 is below `a`, not after it.
        static::assertSame(
            [
                'a1',
                'b',
                'c',
                'c1',
            ],
            $after
        );

        static::assertSame(
            [
                'b',
                'c',
            ],
            $siblings
        );
    }

    #[Test]
    public function theOutermostSiblingsHaveNoneOnOneSide(): void
    {
        $nodes = $this->buildTree();

        static::assertCount(0, $nodes['a']->newNestedSetQuery()->prevSiblings()->get());
        static::assertCount(0, $nodes['c']->newNestedSetQuery()->nextSiblings()->get());
    }
}
