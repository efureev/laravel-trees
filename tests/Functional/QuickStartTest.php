<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Database\Migrate;
use Fureev\Trees\Tests\AbstractTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs docs/QuickStart.md end to end. Every step and every number on that page is asserted
 * here, including the migration written with Migrate::columnsFromModel().
 */
class QuickStartTest extends AbstractTestCase
{
    private function migrate(): void
    {
        Schema::create(
            'categories',
            static function (Blueprint $table) {
                $table->integerIncrements('id');
                $table->string('title');

                Migrate::columnsFromModel($table, Category::class);
            }
        );
    }

    /**
     * Backs docs/QuickStart.md: the migration, the first root, the children, and the numbers
     * the page prints next to each call.
     */
    #[Test]
    public function theQuickStartWorksAsWritten(): void
    {
        $this->migrate();

        // Step: the schema carries the tree columns.
        foreach (['lft', 'rgt', 'lvl', 'parent_id'] as $column) {
            static::assertTrue(Schema::hasColumn('categories', $column), "missing [$column]");
        }

        // Step: the first node has to be made a root explicitly.
        $root = Category::make(['title' => 'Catalogue']);
        $root->makeRoot()->save();

        static::assertTrue($root->isRoot());
        static::assertSame(1, $root->leftValue());
        static::assertSame(2, $root->rightValue());
        static::assertSame(0, $root->levelValue());

        // Step: children are appended to a parent, then saved.
        $shoes = Category::make(['title' => 'Shoes']);
        $shoes->appendTo($root)->save();

        $boots = Category::make(['title' => 'Boots']);
        $boots->appendTo($shoes->refresh())->save();

        $bags = Category::make(['title' => 'Bags']);
        $bags->appendTo($root->refresh())->save();

        // Step: reading back.
        static::assertSame(3, $root->refresh()->descendants()->count());
        static::assertSame(2, $root->children()->count());
        static::assertSame(2, $boots->refresh()->ancestors()->count());
        static::assertSame(
            [
                'Catalogue',
                'Shoes',
            ],
            $boots->ancestors()->get()->pluck('title')->all()
        );

        // Step: the whole tree in one query.
        $tree = Category::query()->defaultOrder()->get()->toTree();

        static::assertCount(1, $tree);
        static::assertSame(4, $tree->totalCount());
        static::assertSame(
            [
                'Shoes',
                'Bags',
            ],
            $tree->first()->children->pluck('title')->all()
        );

        // Step: moving a subtree.
        $shoes->refresh()->appendTo($bags->refresh())->save();

        static::assertTrue($shoes->refresh()->isChildOf($bags->refresh()));
        static::assertTrue($boots->refresh()->isChildOf($bags));

        // Step: deleting lifts the children one level.
        $shoes->refresh()->delete();

        static::assertSame($bags->getKey(), $boots->refresh()->parentValue());
        static::assertSame(3, Category::query()->count());
    }
}
