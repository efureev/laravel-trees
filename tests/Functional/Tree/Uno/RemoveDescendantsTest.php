<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * `removeDescendants()` deletes the subtree with a query and keeps the node. What it does not do
 * is close the span those descendants occupied, and these pin that down rather than wish it
 * away — see the finding in INSPECTION.md.
 */
class RemoveDescendantsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> a (-> a1), b
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $a */
        $a = static::model(['title' => 'a']);
        $a->appendTo($root->refresh())->save();

        /** @var Category $a1 */
        $a1 = static::model(['title' => 'a1']);
        $a1->appendTo($a->refresh())->save();

        /** @var Category $b */
        $b = static::model(['title' => 'b']);
        $b->appendTo($root->refresh())->save();

        return [
            'root' => $root->refresh(),
            'a'    => $a->refresh(),
            'a1'   => $a1->refresh(),
            'b'    => $b->refresh(),
        ];
    }

    #[Test]
    public function itDeletesTheSubtreeAndKeepsTheNode(): void
    {
        $nodes = $this->buildTree();

        $nodes['a']->removeDescendants();

        static::assertSame(
            [
                'root',
                'a',
                'b',
            ],
            Category::query()->defaultOrder()->get()->pluck('title')->all()
        );
        static::assertSame(0, $nodes['a']->refresh()->children()->count());
    }

    /**
     * The node keeps the span its children used. Nothing shifts, so `isLeaf()` — which answers
     * from the bounds — reports a node with no children as not a leaf.
     */
    #[Test]
    public function theVacatedSpanIsLeftBehind(): void
    {
        $nodes = $this->buildTree();

        static::assertSame([2, 5], [$nodes['a']->leftValue(), $nodes['a']->rightValue()]);

        $nodes['a']->removeDescendants();

        $a = $nodes['a']->refresh();

        static::assertSame([2, 5], [$a->leftValue(), $a->rightValue()]);
        static::assertSame(0, $a->children()->count());
        static::assertFalse($a->isLeaf());

        // Neither does the sibling move up into the freed room.
        static::assertSame([6, 7], [$nodes['b']->refresh()->leftValue(), $nodes['b']->rightValue()]);
    }

    /**
     * The leftover room is never reused: a node appended afterwards opens a gap of its own and
     * the empty pair stays inside the parent for good.
     */
    #[Test]
    public function aLaterInsertDoesNotReclaimTheRoom(): void
    {
        $nodes = $this->buildTree();

        $nodes['a']->removeDescendants();

        /** @var Category $fresh */
        $fresh = static::model(['title' => 'fresh']);
        $fresh->appendTo($nodes['a']->refresh())->save();

        static::assertSame([2, 7], [$nodes['a']->refresh()->leftValue(), $nodes['a']->refresh()->rightValue()]);
        static::assertSame([5, 6], [$fresh->refresh()->leftValue(), $fresh->refresh()->rightValue()]);
    }

    /**
     * And none of the three active checks notices: a span wider than its contents is not
     * something HealthyChecker looks for.
     */
    #[Test]
    public function theHealthCheckerReportsNothing(): void
    {
        $nodes = $this->buildTree();

        $nodes['a']->removeDescendants();

        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }
}
