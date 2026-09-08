<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithQuotedColumns as Node;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every tree operation has to survive column names that need quoting.
 */
class QuotedColumnsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Node>
     */
    protected static function modelClass(): string
    {
        return Node::class;
    }

    /**
     * root -> (a -> a1, b)
     *
     * @return array<string, Node>
     */
    private function buildTree(string $prefix): array
    {
        /** @var Node $root */
        $root = static::model(['title' => "$prefix root"]);
        $root->makeRoot()->save();

        /** @var Node $a */
        $a = static::model(['title' => "$prefix a"]);
        $a->appendTo($root)->save();

        /** @var Node $a1 */
        $a1 = static::model(['title' => "$prefix a1"]);
        $a1->appendTo($a)->save();

        /** @var Node $b */
        $b = static::model(['title' => "$prefix b"]);
        $b->appendTo($root->refresh())->save();

        return [
            'root' => $root->refresh(),
            'a'    => $a->refresh(),
            'a1'   => $a1->refresh(),
            'b'    => $b->refresh(),
        ];
    }

    #[Test]
    public function buildsAndKeepsTreeHealthy(): void
    {
        $nodes = $this->buildTree('one');

        static::assertSame(1, $nodes['root']->leftValue());
        static::assertSame(8, $nodes['root']->rightValue());
        static::assertFalse((new HealthyChecker(Node::class))->isBroken());
    }

    #[Test]
    public function movesNodeInsideOneTree(): void
    {
        $nodes = $this->buildTree('one');

        $nodes['a']->appendTo($nodes['b']->refresh())->save();

        static::assertSame(2, $nodes['b']->refresh()->leftValue());
        static::assertTrue($nodes['a']->refresh()->isChildOf($nodes['b']));
        static::assertTrue($nodes['a1']->refresh()->isChildOf($nodes['a']));
        static::assertFalse((new HealthyChecker(Node::class))->isBroken());
    }

    #[Test]
    public function movesNodeBetweenTrees(): void
    {
        $one = $this->buildTree('one');
        $two = $this->buildTree('two');

        $one['a']->appendTo($two['b']->refresh())->save();

        $moved = $one['a']->refresh();

        static::assertSame($two['root']->refresh()->treeValue(), $moved->treeValue());
        static::assertTrue($moved->isChildOf($two['b']->refresh()));
        static::assertSame($moved->treeValue(), $one['a1']->refresh()->treeValue());
        static::assertFalse((new HealthyChecker(Node::class))->isBroken());
    }

    /**
     * `makeRoot()` on an existing node needs `forceSave()`: on its own it changes no attribute,
     * so Eloquent skips the update and `moveNodeAsRoot()` never runs.
     *
     * Note that the promoted node keeps its old `parentId`, so `isRoot()` still answers false —
     * that is a separate defect from the column quoting this suite is about.
     */
    #[Test]
    public function turnsNodeIntoItsOwnRoot(): void
    {
        $nodes = $this->buildTree('one');

        $nodes['a']->refresh()->makeRoot()->forceSave();

        $promoted = $nodes['a']->refresh();
        $child    = $nodes['a1']->refresh();

        static::assertSame(1, $promoted->leftValue());
        static::assertSame(0, $promoted->levelValue());
        static::assertNotSame($nodes['root']->refresh()->treeValue(), $promoted->treeValue());

        // The subtree follows into the new tree.
        static::assertSame($promoted->treeValue(), $child->treeValue());
        static::assertSame(1, $child->levelValue());
        static::assertTrue($child->isChildOf($promoted));
    }

    #[Test]
    public function deletingBranchMovesChildrenToParent(): void
    {
        $nodes = $this->buildTree('one');

        $nodes['a']->refresh()->delete();

        static::assertTrue($nodes['a1']->refresh()->isChildOf($nodes['root']->refresh()));
        static::assertSame(1, $nodes['a1']->levelValue());
        static::assertFalse((new HealthyChecker(Node::class))->isBroken());
    }

    #[Test]
    public function queriesLeaves(): void
    {
        $nodes = $this->buildTree('one');

        $leaves = $nodes['root']->refresh()->leaves()->get()->pluck('title')->all();

        static::assertEqualsCanonicalizing(['one a1', 'one b'], $leaves);
    }
}
