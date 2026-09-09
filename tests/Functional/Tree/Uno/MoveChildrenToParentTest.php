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
}
