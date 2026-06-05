<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Relations\DescendantsRelation;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers eager/lazy loading of the {@see DescendantsRelation}/AncestorsRelation
 * relations and the {@see \Fureev\Trees\Relations\BaseRelation} guards (category D).
 */
class RelationEagerTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Build a tree:
     *   root
     *     - node2
     *       - node3
     */
    private function buildChain(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $node2 */
        $node2 = static::model(['title' => 'level 2']);
        $node2->appendTo($root)->save();

        /** @var Category $node3 */
        $node3 = static::model(['title' => 'level 3']);
        $node3->appendTo($node2)->save();
    }

    #[Test]
    public function eagerLoadsDescendantsForCollection(): void
    {
        $this->buildChain();

        $nodes = Category::query()->defaultOrder()->with('descendants')->get();

        static::assertCount(3, $nodes);

        foreach ($nodes as $node) {
            static::assertTrue($node->relationLoaded('descendants'));
        }

        $byTitle = $nodes->keyBy('title');

        static::assertCount(2, $byTitle['root node']->getRelation('descendants'));
        static::assertCount(1, $byTitle['level 2']->getRelation('descendants'));
        static::assertCount(0, $byTitle['level 3']->getRelation('descendants'));
    }

    #[Test]
    public function eagerLoadsAncestorsForCollection(): void
    {
        $this->buildChain();

        $nodes = Category::query()->defaultOrder()->with('ancestors')->get();

        static::assertCount(3, $nodes);

        $byTitle = $nodes->keyBy('title');

        static::assertCount(0, $byTitle['root node']->getRelation('ancestors'));
        static::assertCount(1, $byTitle['level 2']->getRelation('ancestors'));
        static::assertCount(2, $byTitle['level 3']->getRelation('ancestors'));

        $ancestorsTitles = $byTitle['level 3']->getRelation('ancestors')
            ->pluck('title')
            ->all();
        static::assertEqualsCanonicalizing(['root node', 'level 2'], $ancestorsTitles);
    }

    #[Test]
    public function lazyLoadsRelationsAfterRetrieval(): void
    {
        $this->buildChain();

        /** @var Category $root */
        $root = Category::root()->first();

        static::assertFalse($root->relationLoaded('descendants'));

        $root->load('descendants');

        static::assertTrue($root->relationLoaded('descendants'));
        static::assertCount(2, $root->getRelation('descendants'));
    }

    #[Test]
    public function relationRejectsNonTreeParent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model must be a node.');

        new DescendantsRelation(Category::query(), new NonTreeModel());
    }
}
