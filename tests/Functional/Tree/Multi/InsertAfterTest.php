<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class InsertAfterTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function insertAfterRoot(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        $this->expectException(UniqueRootException::class);

        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.2']);
        $node21->insertAfter($modelRoot)->save();
    }

    #[Test]
    public function insertAfter(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();
        $modelRoot->refresh();

        /** @var MultiCategory $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->insertAfter($node21)->save();

        $modelRoot->refresh();
        $node21->refresh();

        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(3, $node21->rightValue());

        static::assertSame(1, $node31->levelValue());
        static::assertEquals(4, $node31->leftValue());
        static::assertEquals(5, $node31->rightValue());

        /** @var MultiCategory $node41 */
        $node41 = static::model(['title' => 'child 4.1']);
        $node41->insertAfter($node21)->save();

        $modelRoot->refresh();
        $node31->refresh();
        $node21->refresh();

        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(8, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(3, $node21->rightValue());

        static::assertSame(1, $node41->levelValue());
        static::assertEquals(4, $node41->leftValue());
        static::assertEquals(5, $node41->rightValue());

        static::assertSame(1, $node31->levelValue());
        static::assertEquals(6, $node31->leftValue());
        static::assertEquals(7, $node31->rightValue());

        static::assertTrue($node41->isEqualTo($node31->prev()->first()));
        static::assertTrue($node21->isEqualTo($node41->prev()->first()));

        static::assertNull($node21->prev()->first());
        static::assertNull($node31->next()->first());

        static::assertTrue($node31->isEqualTo($node41->next()->first()));
        static::assertTrue($node41->isEqualTo($node21->next()->first()));
    }
}
