<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * `removeDescendants()` deletes the subtree and keeps the node. It used to leave the node as
 * wide as the subtree it no longer had, so these pin down that the room is given back.
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
     * root(1..8) -> a(2..5) -> a1(3..4), b(6..7)
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

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    private function bounds(): array
    {
        return Category::query()
            ->defaultOrder()
            ->get()
            ->mapWithKeys(
                static fn(Category $n) => [
                    $n->title => [
                        $n->leftValue(),
                        $n->rightValue(),
                    ],
                ]
            )
            ->all();
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
     * The node becomes a leaf and everything positioned after it moves up by the width that was
     * freed. The whole tree closes ranks.
     */
    #[Test]
    public function theVacatedSpanIsClosed(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(
            [
                'root' => [
                    1,
                    8,
                ],
                'a'    => [
                    2,
                    5,
                ],
                'a1'   => [
                    3,
                    4,
                ],
                'b'    => [
                    6,
                    7,
                ],
            ],
            $this->bounds()
        );

        $nodes['a']->removeDescendants();

        static::assertSame(
            [
                'root' => [
                    1,
                    6,
                ],
                'a'    => [
                    2,
                    3,
                ],
                'b'    => [
                    4,
                    5,
                ],
            ],
            $this->bounds()
        );

        // The model in hand agrees with the row, and is not left dirty by saying so.
        static::assertSame([2, 3], [$nodes['a']->leftValue(), $nodes['a']->rightValue()]);
        static::assertSame([], $nodes['a']->getDirty());
        static::assertTrue($nodes['a']->isLeaf());
    }

    #[Test]
    public function aLaterInsertUsesTheFreedRoom(): void
    {
        $nodes = $this->buildTree();

        $nodes['a']->removeDescendants();

        /** @var Category $fresh */
        $fresh = static::model(['title' => 'fresh']);
        $fresh->appendTo($nodes['a']->refresh())->save();

        static::assertSame(
            [
                'root'  => [
                    1,
                    8,
                ],
                'a'     => [
                    2,
                    5,
                ],
                'fresh' => [
                    3,
                    4,
                ],
                'b'     => [
                    6,
                    7,
                ],
            ],
            $this->bounds()
        );
    }

    #[Test]
    public function theTreeStaysHealthy(): void
    {
        $nodes = $this->buildTree();

        $nodes['a']->removeDescendants();

        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * A leaf has nothing below it, so there is nothing to delete and no room to give back.
     */
    #[Test]
    public function callingItOnALeafChangesNothing(): void
    {
        $nodes = $this->buildTree();

        $before = $this->bounds();

        $nodes['b']->removeDescendants();

        static::assertSame($before, $this->bounds());
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * The loaded relation is emptied along with the rows, rather than left holding models that
     * are no longer there.
     */
    #[Test]
    public function aLoadedChildrenRelationIsEmptied(): void
    {
        $nodes = $this->buildTree();

        $a = Category::query()->with('children')->find($nodes['a']->getKey());

        static::assertCount(1, $a->children);

        $a->removeDescendants();

        static::assertCount(0, $a->children);
    }
}
