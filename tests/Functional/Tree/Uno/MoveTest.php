<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class MoveTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function moveAppend(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->prependTo($modelRoot)->save();

        static::assertSame(1, $node21->levelValue());

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        static::assertSame(2, $node31->levelValue());
        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());

        $node21->refresh();
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());

        // Move to Root to the Beginning
        $node31->prependTo($modelRoot)->save();
        $node31->refresh();

        static::assertSame(1, $node31->levelValue());
        static::assertEquals(2, $node31->leftValue());
        static::assertEquals(3, $node31->rightValue());

        $node21->refresh();
        static::assertEquals(4, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());

        $modelRoot->refresh();
        static::assertTrue($modelRoot->isEqualTo($node31->parent));
        static::assertCount(2, $modelRoot->children);


        $node31->appendTo($node21)->save();
        $node31->refresh();
        static::assertSame(2, $node31->levelValue());
        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());

        $node21->refresh();
        $modelRoot->refresh();

        static::assertTrue($node21->isEqualTo($node31->parent));
        static::assertCount(1, $modelRoot->children);
        static::assertCount(1, $node21->children);
    }
}
