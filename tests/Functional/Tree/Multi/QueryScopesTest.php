<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs docs/UseCases.md and docs/ReceivingNodes.md. These scopes are the ones the use cases
 * lean on, and none of them had coverage.
 */
class QueryScopesTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * Each tree: root -> a -> a1, plus b under the root.
     *
     * @return array<string, MultiCategory>
     */
    private function buildTree(string $prefix): array
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => "$prefix root"]);
        $root->makeRoot()->save();

        /** @var MultiCategory $a */
        $a = static::model(['title' => "$prefix a"]);
        $a->appendTo($root)->save();

        /** @var MultiCategory $a1 */
        $a1 = static::model(['title' => "$prefix a1"]);
        $a1->appendTo($a->refresh())->save();

        /** @var MultiCategory $b */
        $b = static::model(['title' => "$prefix b"]);
        $b->appendTo($root->refresh())->save();

        return [
            'root' => $root->refresh(),
            'a'    => $a->refresh(),
            'a1'   => $a1->refresh(),
            'b'    => $b->refresh(),
        ];
    }

    /**
     * Backs docs/UseCases.md: byTree() narrows a query to one tree, which is how a tenant is
     * isolated.
     */
    #[Test]
    public function byTreeNarrowsToASingleTree(): void
    {
        $one = $this->buildTree('one');
        $this->buildTree('two');

        static::assertSame(8, MultiCategory::query()->count());

        $titles = MultiCategory::query()
            ->byTree($one['root']->treeValue())
            ->get()
            ->pluck('title')
            ->all();

        static::assertCount(4, $titles);
        foreach ($titles as $title) {
            static::assertStringStartsWith('one ', $title);
        }
    }

    /**
     * Backs docs/UseCases.md: toLevel() keeps everything down to the given depth — a menu that
     * shows two levels is one query, not a walk.
     */
    #[Test]
    public function toLevelKeepsEverythingDownToThatDepth(): void
    {
        $one = $this->buildTree('one');

        $titles = MultiCategory::query()
            ->byTree($one['root']->treeValue())
            ->toLevel(1)
            ->defaultOrder()
            ->get()
            ->pluck('title')
            ->all();

        // root (0), a (1) and b (1) — a1 sits at level 2 and is left out.
        static::assertSame(
            [
                'one root',
                'one a',
                'one b',
            ],
            $titles
        );
    }

    /**
     * Backs docs/ReceivingNodes.md: byLevel() takes one level exactly, byParent() the direct
     * children of a node.
     */
    #[Test]
    public function byLevelAndByParentSelectOneSliceEach(): void
    {
        $one = $this->buildTree('one');

        $level1 = MultiCategory::query()
            ->byTree($one['root']->treeValue())
            ->byLevel(1)
            ->defaultOrder()
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(
            [
                'one a',
                'one b',
            ],
            $level1
        );

        $children = MultiCategory::query()
            ->byParent($one['a'])
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(['one a1'], $children);
    }

    /**
     * Backs docs/Performance.md: descendantsQuery($level) cuts the depth in the query rather
     * than filtering afterwards in PHP.
     */
    #[Test]
    public function descendantsQueryCanBeLimitedByDepth(): void
    {
        $one = $this->buildTree('one');

        static::assertSame(3, $one['root']->descendants()->count());

        $shallow = $one['root']
            ->newNestedSetQuery()
            ->descendantsQuery(1)
            ->get()
            ->pluck('title')
            ->all();

        static::assertEqualsCanonicalizing(
            [
                'one a',
                'one b',
            ],
            $shallow
        );
    }
}
