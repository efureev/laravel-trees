<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Tests\Functional\Concerns\CountsStatements;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs the "Queries" column of docs/ApiReference.md.
 *
 * Those numbers were written from a run and then drifted, because the code around them changed
 * and nothing read them again. Each row measured here is one row of that table, so a change in
 * cost shows up as a failing test rather than as a stale document.
 */
class DocumentedQueryCountsTest extends AbstractFunctionalTreeTestCase
{
    use CountsStatements;

    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> a -> a1, plus b and c alongside a.
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

        $nodes = [
            'root' => $root,
            'a'    => $a,
            'a1'   => $a1,
        ];

        foreach (['b', 'c'] as $title) {
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

    /**
     * A single tree checks that no root exists yet, then inserts.
     */
    #[Test]
    public function saveAsRootCostsTwo(): void
    {
        /** @var Category $fresh */
        $fresh = static::model(['title' => 'root']);

        static::assertSame(2, $this->statements(static fn() => $fresh->saveAsRoot()));
    }

    /**
     * Already a root and nothing changed, so the save finds nothing to write.
     */
    #[Test]
    public function saveAsRootOnAnUnchangedRootCostsNothing(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(0, $this->statements(static fn() => $nodes['root']->saveAsRoot()));
    }

    #[Test]
    public function movingASiblingCostsSix(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(6, $this->statements(static fn() => $nodes['c']->up()));
    }

    #[Test]
    public function movingASiblingDownCostsSix(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(6, $this->statements(static fn() => $nodes['b']->down()));
    }

    /**
     * Refresh, lift the children — three statements of its own — remove the row, close the span.
     */
    #[Test]
    public function deletingANodeWithChildrenCostsSeven(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(7, $this->statements(static fn() => $nodes['a']->delete()));
    }

    /**
     * Read the parent, shift the descendants, re-parent them, collapse the node's own span.
     */
    #[Test]
    public function liftingChildrenCostsFour(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(4, $this->statements(static fn() => $nodes['a']->moveChildrenToParent()));
    }

    #[Test]
    public function removingDescendantsCostsTwo(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(2, $this->statements(static fn() => $nodes['a']->removeDescendants()));
    }

    #[Test]
    public function parentByLevelCostsOne(): void
    {
        $nodes = $this->buildTree();

        static::assertSame(1, $this->statements(static fn() => $nodes['a1']->parentByLevel(1)));
    }
}
