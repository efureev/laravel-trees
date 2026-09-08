<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * beforeDelete() asked the database how many children a node has and handed the answer to
 * onDeletingNodeHasChildren(), whose body is a commented-out throw — a count on every delete
 * that nothing reads.
 */
class DeleteQueryCountTest extends AbstractFunctionalTreeTestCase
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
    private function countQueries(): array
    {
        return array_values(
            array_filter(
                array_column(static::model()->getConnection()->getQueryLog(), 'query'),
                static fn(string $sql) => str_contains(strtolower($sql), 'select count(')
            )
        );
    }

    #[Test]
    public function deletingALeafAsksForNoCounts(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($root)->save();

        $leaf->refresh();
        static::model()->getConnection()->flushQueryLog();

        $leaf->delete();

        static::assertSame(
            [],
            $this->countQueries(),
            'a leaf carries its own answer in its bounds, no count is needed'
        );
    }

    #[Test]
    public function deletingABranchStillMovesChildrenUp(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root)->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($branch->refresh())->save();

        $branch->refresh()->delete();

        static::assertSame($root->refresh()->getKey(), $child->refresh()->parentValue());
        static::assertSame(1, $child->levelValue());
    }
}
