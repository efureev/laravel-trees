<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * beforeDelete() refreshes the node so afterDelete() closes the gap by current bounds.
 * Model::refresh() also reloads every relation that happens to be loaded, one query each,
 * and linkNodes() leaves `children` (and `parent`) set on every node it walks.
 */
class DeleteRefreshTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * @return string[]
     */
    private function selects(): array
    {
        return array_values(
            array_filter(
                array_column(static::model()->getConnection()->getQueryLog(), 'query'),
                static fn(string $sql) => str_starts_with(strtolower($sql), 'select')
            )
        );
    }

    #[Test]
    public function deletingANodeFromATreeRefreshesOnlyItself(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($root)->save();

        // toTree() runs linkNodes(), which sets `children` on every node and `parent` too.
        $tree = Category::query()->defaultOrder()->get()->toTree(setParentRelations: true);

        /** @var Category $target */
        $target = $tree->first()->children->first();

        static::assertTrue($target->relationLoaded('children'));
        static::assertTrue($target->relationLoaded('parent'));

        static::model()->getConnection()->flushQueryLog();

        $target->delete();

        static::assertCount(
            1,
            $this->selects(),
            'deleting a node should refresh that node, not every relation hanging off it'
        );
    }

    #[Test]
    public function staleBoundsAreRefreshedBeforeDelete(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $first */
        $first = static::model(['title' => 'first']);
        $first->appendTo($root)->save();

        // Read it, then move the ground under it: the sibling shifts `first` in the database.
        /** @var Category $stale */
        $stale = Category::query()->find($first->getKey());

        /** @var Category $second */
        $second = static::model(['title' => 'second']);
        $second->prependTo($root->refresh())->save();

        static::assertNotSame($stale->leftValue(), $first->refresh()->leftValue());

        $stale->delete();

        static::assertSame(4, $root->refresh()->rightValue());
        static::assertFalse((new HealthyChecker(Category::class))->isBroken());
    }
}
