<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Shifting the bounds is the hottest path of the package: every insert, move and delete
 * goes through UseNestedSet::shift().
 */
class ShiftTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Statements that write the bound columns, issued since the log was last cleared.
     *
     * @return string[]
     */
    private function boundsUpdates(): array
    {
        $left  = (string)static::model()->leftAttribute();
        $right = (string)static::model()->rightAttribute();

        return array_values(
            array_filter(
                array_column(static::model()->getConnection()->getQueryLog(), 'query'),
                static fn(string $sql) => str_starts_with(strtolower($sql), 'update')
                    && (str_contains($sql, $left) || str_contains($sql, $right))
            )
        );
    }

    #[Test]
    public function insertIssuesASingleBoundsUpdate(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $first */
        $first = static::model(['title' => 'first']);
        $first->appendTo($root)->save();

        static::model()->getConnection()->flushQueryLog();

        /** @var Category $second */
        $second = static::model(['title' => 'second']);
        $second->appendTo($root->refresh())->save();

        static::assertCount(
            1,
            $this->boundsUpdates(),
            'shifting the bounds must take a single statement, not one per column'
        );
    }

    #[Test]
    public function shiftMovesOnlyTheBoundsInsideTheRange(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root)->save();

        $branchLeftBefore  = $branch->refresh()->leftValue();
        $branchRightBefore = $branch->rightValue();

        // Appending inside the branch pushes only its right bound, never its left one.
        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($branch)->save();

        $branch->refresh();

        static::assertSame($branchLeftBefore, $branch->leftValue(), 'left bound must stay put');
        static::assertSame(($branchRightBefore + 2), $branch->rightValue(), 'right bound must shift');
    }

    #[Test]
    public function deleteShiftsTheRemainingBoundsBack(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        $titles = [
            'a',
            'b',
            'c',
        ];
        $nodes  = [];
        foreach ($titles as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root->refresh())->save();
            $nodes[$title] = $node;
        }

        static::assertSame(8, $root->refresh()->rightValue());

        $nodes['a']->refresh()->delete();

        static::assertSame(6, $root->refresh()->rightValue());
        static::assertSame(2, $nodes['b']->refresh()->leftValue());
        static::assertSame(4, $nodes['c']->refresh()->leftValue());
    }
}
