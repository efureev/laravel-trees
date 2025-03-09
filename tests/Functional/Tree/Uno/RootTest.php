<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\NotSupportedException;
use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class RootTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function createRootModel(): void
    {
        /** @var Category $model */
        $model = static::model(['title' => 'root node']);

        $model->makeRoot()->save();

        static::assertSame(1, $model->id);
        static::assertTrue($model->isRoot());

        static::assertNotNull($model->getRoot());
        static::assertInstanceOf(static::modelClass(), $model->getRoot());

        static::assertEquals($model->id, $model->getRoot()->id);
        static::assertEquals($model->title, $model->getRoot()->title);
        static::assertEquals(1, $model->leftValue());
        static::assertEquals(2, $model->rightValue());
        static::assertEquals($model->lvl, $model->getRoot()->lvl);
        static::assertSame(0, $model->getRoot()->lvl);

        static::assertEmpty($model->parents());
        static::assertTrue($model->isLeaf());
    }

    #[Test]
    public function createSeveralRoot(): void
    {
        /** @var Category $model */
        $model = static::model(['title' => 'root 1']);
        $model->makeRoot()->save();

        $this->expectException(UniqueRootException::class);

        $model = static::model(['title' => 'root 2']);
        $model->makeRoot()->save();
    }

    public function testBaseSaveException(): void
    {
        $model = static::model(['id' => 2, 'title' => 'node']);
        $this->expectException(NotSupportedException::class);
        $model->save();
    }
}
