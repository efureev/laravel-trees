<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class UpTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function up(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($modelRoot)->save();

        /** @var Category $node41 */
        $node41 = static::model(['title' => 'child 4.1']);
        $node41->appendTo($modelRoot)->save();

        //

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->up());
        $node31->refresh();
        static::assertEquals(2, $node31->leftValue());
        static::assertFalse($node31->isForceSaving());

        //

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;
        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());

        static::assertFalse($node31->up());
        $node31->refresh();
        static::assertEquals(2, $node31->leftValue());
        static::assertFalse($node31->isForceSaving());

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;
        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());
    }

    #[Test]
    public function upMoreOneTime(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($modelRoot)->save();

        /** @var Category $node41 */
        $node41 = static::model(['title' => 'child 4.1']);
        $node41->appendTo($modelRoot)->save();

        //

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node41->up());
        $node41->refresh();
        $node21->refresh();
        $node31->refresh();
        static::assertEquals(4, $node41->leftValue());
        static::assertFalse($node41->isForceSaving());

        //

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;
        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

        static::assertTrue($node41->up());
        $node41->refresh();
        $node21->refresh();
        $node31->refresh();
        static::assertEquals(2, $node41->leftValue());
        static::assertFalse($node41->isForceSaving());

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;
        static::assertEquals(['child 4.1', 'child 2.1', 'child 3.1'], $children->toArray());


        static::assertFalse($node41->up());
        $node41->refresh();
        static::assertEquals(2, $node41->leftValue());
        static::assertFalse($node41->isForceSaving());

        $children = $modelRoot->children()->defaultOrder()->get()->map->title;
        static::assertEquals(['child 4.1', 'child 2.1', 'child 3.1'], $children->toArray());
    }
}
