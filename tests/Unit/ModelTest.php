<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\CategoryWithUuid;
use PHPUnit\Framework\Attributes\Test;

class ModelTest extends AbstractUnitTestCase
{
    #[Test]
    public function makeModel(): void
    {
        $model = new Category(['title' => 'Root node']);

        static::assertFalse($model->isMulti());
        static::assertFalse($model->getTreeConfig()->isMulti());
        static::assertFalse($model->getTreeConfig()->isSoftDelete);
    }

    #[Test]
    public function makeModelUuid(): void
    {
        $model = new CategoryWithUuid(['title' => 'Root node']);

        static::assertFalse($model->isMulti());
        static::assertFalse($model->getTreeConfig()->isMulti());
        static::assertFalse($model->getTreeConfig()->isSoftDelete);
    }

    #[Test]
    public function checkCasts(): void
    {
        $model = new Category(['title' => 'Root node']);
        $casts = $model->getCasts();

        static::assertEquals('integer', $casts[(string)$model->leftAttribute()]);
        static::assertEquals('integer', $casts[(string)$model->rightAttribute()]);
        static::assertEquals('integer', $casts[(string)$model->levelAttribute()]);

        static::assertEquals($model->getKeyType(), $casts[(string)$model->parentAttribute()]);
    }

    #[Test]
    public function checkCastsUuid(): void
    {
        $model = new CategoryWithUuid(['title' => 'Root node']);

        $casts = $model->getCasts();

        static::assertEquals('integer', $casts[(string)$model->leftAttribute()]);
        static::assertEquals('integer', $casts[(string)$model->rightAttribute()]);
        static::assertEquals('integer', $casts[(string)$model->levelAttribute()]);

        static::assertEquals($model->getKeyType(), $casts[(string)$model->parentAttribute()]);
    }
}
