<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Eager/lazy loading of ancestors/descendants relations on multi-trees (category D).
 */
class RelationEagerTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * @return array{0: MultiCategory, 1: MultiCategory, 2: MultiCategory}
     */
    private function buildTree(string $rootTitle): array
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => $rootTitle]);
        $root->makeRoot()->save();

        /** @var MultiCategory $node2 */
        $node2 = static::model(['title' => $rootTitle . ' / level 2']);
        $node2->appendTo($root)->save();

        /** @var MultiCategory $node3 */
        $node3 = static::model(['title' => $rootTitle . ' / level 3']);
        $node3->appendTo($node2)->save();

        $root->refresh();
        $node2->refresh();
        $node3->refresh();

        return [
            $root,
            $node2,
            $node3,
        ];
    }

    #[Test]
    public function eagerLoadingIsScopedToSingleTree(): void
    {
        [$root1] = $this->buildTree('tree 1');
        // A second, independent tree that must not leak into the first tree relations.
        $this->buildTree('tree 2');

        $nodes = MultiCategory::byTree($root1->tree_id)
            ->defaultOrder()
            ->with(['ancestors', 'descendants'])
            ->get();

        static::assertCount(3, $nodes);

        $byTitle = $nodes->keyBy('title');

        // Descendants are scoped to the first tree only.
        static::assertCount(2, $byTitle['tree 1']->getRelation('descendants'));
        static::assertCount(1, $byTitle['tree 1 / level 2']->getRelation('descendants'));
        static::assertCount(0, $byTitle['tree 1 / level 3']->getRelation('descendants'));

        // Ancestors are scoped to the first tree only.
        static::assertCount(0, $byTitle['tree 1']->getRelation('ancestors'));
        static::assertCount(1, $byTitle['tree 1 / level 2']->getRelation('ancestors'));
        static::assertCount(2, $byTitle['tree 1 / level 3']->getRelation('ancestors'));

        $ancestorTitles = $byTitle['tree 1 / level 3']->getRelation('ancestors')
            ->pluck('title')
            ->all();
        static::assertEqualsCanonicalizing(['tree 1', 'tree 1 / level 2'], $ancestorTitles);
    }
}
