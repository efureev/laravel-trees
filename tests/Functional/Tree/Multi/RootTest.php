<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class RootTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function createRoot(): void
    {
        /** @var MultiCategory $model */
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

        static::assertEquals($model->tree_id, $model->getRoot()->tree_id);
        static::assertSame(1, $model->getRoot()->tree_id);

        static::assertEmpty($model->parents());
    }

    #[Test]
    public function createSeveralRoot(): void
    {
        /** @var MultiCategory $model */
        $model = static::model(['title' => 'root 1']);
        $model->makeRoot()->save();
        static::assertSame(1, $model->tree_id);

        $model2 = static::model(['title' => 'root 2']);
        $model2->makeRoot()->save();
        static::assertSame(2, $model2->tree_id);
    }

    #[Test]
    public function createSeveralRootWithoutMarkThemAsRoot(): void
    {
        /** @var MultiCategory $model */
        $model = static::model(['title' => 'root 1']);
        $model->save();
        static::assertSame(1, $model->tree_id);
        static::assertEquals(1, $model->leftValue());
        static::assertEquals(2, $model->rightValue());
        static::assertEmpty($model->parents());
        static::assertTrue($model->isLeaf());

        $model2 = static::model(['title' => 'root 2']);
        $model2->save();

        static::assertSame(2, $model2->tree_id);
        static::assertEquals(1, $model2->leftValue());
        static::assertEquals(2, $model2->rightValue());
        static::assertEmpty($model2->parents());

        static::assertNotEquals($model->tree_id, $model2->tree_id);
        static::assertTrue($model2->isLeaf());
    }
}
