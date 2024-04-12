<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\models\Category;

/**
 * @deprecated
 */
class FixTreeTestV3 extends AbstractV3UnitTestCase
{
    protected static $modelClass = Category::class;

    public function testFixWithoutErrors(): void
    {
        static::makeTree(null, 1, 2, 4);

        static::assertEquals(0, Category::fixTree());
    }

    public function testFixWithErrors(): void
    {
        static::makeTree(null, 1, 2, 3);

        /** @var Category $brokenModel */
        $brokenModel = static::$modelClass::find(4);
        $brokenModel->setAttribute($brokenModel->rightAttribute()->name(), -130);
        $brokenModel->save();

        $oddness = static::$modelClass::countErrors('oddness');

        static::assertEquals(1, $oddness);

        Category::fixTree();

        static::assertEquals(0, static::$modelClass::countErrors('oddness'));
    }

}
