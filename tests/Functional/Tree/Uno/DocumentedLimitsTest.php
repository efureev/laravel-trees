<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs docs/Limitations.md. Every claim on that page has to be provable, and these are the
 * ones nothing else in the suite covered.
 */
class DocumentedLimitsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> branch -> leaf
     *
     * @return array<string, Category>
     */
    private function buildBranch(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root)->save();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
        ];
    }

    /**
     * Backs docs/Limitations.md: deleting through a query leaves the bounds untouched, so the
     * tree keeps a hole where the node used to be. Only a delete through the model repairs it.
     */
    #[Test]
    public function deletingThroughAQueryLeavesAHoleInTheTree(): void
    {
        $nodes = $this->buildBranch();

        static::assertSame(6, $nodes['root']->rightValue());

        Category::query()->whereKey($nodes['leaf']->getKey())->delete();

        // The row is gone, but nobody closed the gap it left: the root still spans six.
        static::assertNull(Category::query()->find($nodes['leaf']->getKey()));
        static::assertSame(6, $nodes['root']->refresh()->rightValue());
        static::assertSame(5, $nodes['branch']->refresh()->rightValue());

        // Worse: a later, perfectly legal delete cannot put it right, because it trusts the
        // bounds the query left behind. Deleting the branch leaves the root alone in the tree,
        // and a lone root must span 1..2 — it spans 1..4.
        $nodes['branch']->refresh()->delete();

        static::assertSame(1, Category::query()->count());
        static::assertSame(1, $nodes['root']->refresh()->leftValue());
        static::assertSame(4, $nodes['root']->rightValue());
    }

    /**
     * Backs docs/Limitations.md: isChildOf() asks "is this node inside that node's bounds",
     * so it answers true for a descendant at any depth, not only a direct child.
     */
    #[Test]
    public function isChildOfIsTrueForADescendantAtAnyDepth(): void
    {
        $nodes = $this->buildBranch();

        static::assertSame($nodes['branch']->getKey(), $nodes['leaf']->parentValue());
        static::assertNotSame($nodes['root']->getKey(), $nodes['leaf']->parentValue());

        // Direct child of `branch`, grandchild of `root` — true for both.
        static::assertTrue($nodes['leaf']->isChildOf($nodes['branch']));
        static::assertTrue($nodes['leaf']->isChildOf($nodes['root']));
    }

    /**
     * Backs docs/Limitations.md: the descendants relation carries no ORDER BY, while ancestors
     * does. The order descendants come back in is down to the query plan, so documentation must
     * not promise one.
     */
    #[Test]
    public function descendantsCarryNoOrderByWhileAncestorsDo(): void
    {
        $nodes = $this->buildBranch();

        static::assertStringNotContainsStringIgnoringCase(
            'order by',
            $nodes['root']->descendants()->toSql()
        );
        static::assertStringContainsStringIgnoringCase(
            'order by',
            $nodes['leaf']->ancestors()->toSql()
        );
    }

    /**
     * Backs docs/Troubleshooting.md: HealthyChecker runs three of the four checks — the missing
     * parent one is commented out of its list — so an orphaned node passes unnoticed. A clean
     * report means "none of the three found anything", not "the tree is sound".
     */
    #[Test]
    public function theCheckerDoesNotCatchAnOrphanedNode(): void
    {
        $nodes = $this->buildBranch();

        static::assertFalse((new HealthyChecker(Category::class))->isBroken());

        // Remove the middle node behind the package's back: the leaf now points at a row that
        // no longer exists, and the bounds have a hole in them.
        Category::query()->whereKey($nodes['branch']->getKey())->delete();

        static::assertNull(Category::query()->find($nodes['branch']->getKey()));
        static::assertSame(
            $nodes['branch']->getKey(),
            $nodes['leaf']->refresh()->parentValue()
        );

        // And the checker still reports a healthy tree.
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());

        // The check that would have caught it exists, but is not part of HealthyChecker.
        static::assertSame(1, (new \Fureev\Trees\Healthy\MissingParentCheck(Category::class))->check());
    }
}
