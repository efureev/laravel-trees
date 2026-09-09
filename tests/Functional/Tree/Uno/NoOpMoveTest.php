<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Moving a node to the place it already occupies. The bound shift asked for is empty, and
 * `shift()` returns before touching anything — the branch that guards against a pointless or
 * inverted range.
 */
class NoOpMoveTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> a, b, c
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
            $nodes[$title] = $node;
        }

        foreach ($nodes as $key => $node) {
            $nodes[$key] = $node->refresh();
        }

        return $nodes;
    }

    #[Test]
    public function insertingAfterTheSiblingItAlreadyFollowsChangesNothing(): void
    {
        $nodes = $this->buildTree();

        $before = Category::query()->defaultOrder()->get()->pluck('title')->all();

        // forceSave(), not save(): a positioning call changes no attribute, so a clean
        // model is skipped before the move is ever attempted.
        $nodes['b']->insertAfter($nodes['a'])->forceSave();

        static::assertSame($before, Category::query()->defaultOrder()->get()->pluck('title')->all());
        static::assertSame([4, 5], [$nodes['b']->refresh()->leftValue(), $nodes['b']->refresh()->rightValue()]);
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }

    #[Test]
    public function insertingBeforeTheSiblingItAlreadyPrecedesChangesNothing(): void
    {
        $nodes = $this->buildTree();

        $before = Category::query()->defaultOrder()->get()->pluck('title')->all();

        $nodes['b']->insertBefore($nodes['c'])->forceSave();

        static::assertSame($before, Category::query()->defaultOrder()->get()->pluck('title')->all());
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }
}
