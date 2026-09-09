<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * `moveChildrenToParent()` is the hook the delete strategy calls; it is not meant to be invoked
 * on its own. A root has no parent to move children to, and the refusal has to come before
 * anything is written.
 */
class MoveChildrenToParentTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> child -> sub
     *
     * @return array<string, Category>
     */
    private function buildChain(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        /** @var Category $sub */
        $sub = static::model(['title' => 'sub']);
        $sub->appendTo($child->refresh())->save();

        return [
            'root'  => $root->refresh(),
            'child' => $child->refresh(),
            'sub'   => $sub->refresh(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function bounds(Category $node): array
    {
        $node->refresh();

        return [
            $node->leftValue(),
            $node->rightValue(),
            $node->levelValue(),
        ];
    }

    #[Test]
    public function callingItOnARootIsRefused(): void
    {
        $nodes = $this->buildChain();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('has none');

        $nodes['root']->moveChildrenToParent();
    }

    /**
     * The important half. The descendants used to be shifted before the parent was looked at,
     * so the call died with a raw `Error` and left the tree renumbered behind it.
     */
    #[Test]
    public function aRefusedCallWritesNothing(): void
    {
        $nodes = $this->buildChain();

        $before = [
            $this->bounds($nodes['root']),
            $this->bounds($nodes['child']),
            $this->bounds($nodes['sub']),
        ];

        try {
            $nodes['root']->moveChildrenToParent();
            static::fail('a root has no parent, the call must be refused');
        } catch (Throwable) {
            // expected
        }

        static::assertSame(
            $before,
            [
                $this->bounds($nodes['root']),
                $this->bounds($nodes['child']),
                $this->bounds($nodes['sub']),
            ]
        );
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * root -> A -> A1 -> A1a, and B -> B1 alongside. `A1a` and `B1` sit at the same level, which
     * is all the re-parenting used to match on: deleting `A1` handed `B1` to `A`, and its bounds
     * said it was inside `B`.
     *
     * @return array<string, Category>
     */
    private function buildCousinTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['A' => 'A1', 'B' => 'B1'] as $branchTitle => $childTitle) {
            /** @var Category $branch */
            $branch = static::model(['title' => $branchTitle]);
            $branch->appendTo($root->refresh())->save();

            /** @var Category $child */
            $child = static::model(['title' => $childTitle]);
            $child->appendTo($branch->refresh())->save();

            $nodes[$branchTitle] = $branch;
            $nodes[$childTitle]  = $child;
        }

        /** @var Category $deepest */
        $deepest = static::model(['title' => 'A1a']);
        $deepest->appendTo($nodes['A1']->refresh())->save();

        foreach ($nodes as $key => $node) {
            $nodes[$key] = $node->refresh();
        }

        $nodes['A1a'] = $deepest->refresh();

        return $nodes;
    }

    #[Test]
    public function deletingANodeDoesNotReparentCousins(): void
    {
        $nodes = $this->buildCousinTree();

        $nodes['A1']->refresh()->delete();

        $b1 = Category::query()->whereKey($nodes['B1']->getKey())->first();

        static::assertSame($nodes['B']->getKey(), $b1->parentValue());

        $a1a = Category::query()->whereKey($nodes['A1a']->getKey())->first();

        static::assertSame($nodes['A']->getKey(), $a1a->parentValue());
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * Called on a node that stays, the children become its siblings and it becomes a leaf. The
     * tree keeps the width it had, because the node gives up exactly what the children take.
     */
    #[Test]
    public function liftingChildrenLeavesTheTreeValid(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root->refresh())->save();

        /** @var Category $sub */
        $sub = static::model(['title' => 'sub']);
        $sub->appendTo($child->refresh())->save();

        $child = $child->refresh();

        $child->moveChildrenToParent();

        static::assertSame(
            [
                'root'  => [
                    1,
                    6,
                ],
                'child' => [
                    2,
                    3,
                ],
                'sub'   => [
                    4,
                    5,
                ],
            ],
            Category::query()
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
                ->all()
        );

        static::assertSame($root->getKey(), $sub->refresh()->parentValue());
        static::assertSame(1, $sub->levelValue());
        static::assertTrue($child->isLeaf());
        static::assertSame([], $child->getDirty());

        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    /**
     * Nothing outside the node moves: the width it gives up is exactly the width the children
     * take, so the root's own bounds are untouched.
     */
    #[Test]
    public function liftingChildrenDoesNotChangeTheTreeWidth(): void
    {
        $nodes = $this->buildCousinTree();

        $before = [
            $nodes['root']->leftValue(),
            $nodes['root']->rightValue(),
        ];

        $nodes['A1']->refresh()->moveChildrenToParent();

        static::assertSame(
            $before,
            [
                $nodes['root']->refresh()->leftValue(),
                $nodes['root']->rightValue(),
            ]
        );
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    #[Test]
    public function liftingChildrenOfALeafChangesNothing(): void
    {
        $nodes = $this->buildCousinTree();

        $before = Category::query()->defaultOrder()->get()
            ->map(static fn(Category $n) => [$n->leftValue(), $n->rightValue()])->all();

        $nodes['A1a']->refresh()->moveChildrenToParent();

        static::assertSame(
            $before,
            Category::query()->defaultOrder()->get()
                ->map(static fn(Category $n) => [$n->leftValue(), $n->rightValue()])->all()
        );
    }

    /**
     * The loaded relation is emptied along with the move, rather than left holding children the
     * node no longer has.
     */
    #[Test]
    public function aLoadedChildrenRelationIsEmptied(): void
    {
        $nodes = $this->buildCousinTree();

        $a1 = Category::query()->with('children')->find($nodes['A1']->getKey());

        static::assertCount(1, $a1->children);

        $a1->moveChildrenToParent();

        static::assertCount(0, $a1->children);
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }
}
