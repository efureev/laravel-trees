<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\models\Category;

class NodeBuilderHealthyTreeTest extends AbstractUnitTestCase
{
    protected static $modelClass = Category::class;

    public function testCountErrors(): void
    {
        static::makeTree(null, 1, 3, 2, 1, 1);

        $data = Category::countErrors();

        static::assertEquals(
            [
                "oddness"        => 0,
                "duplicates"     => 0,
                "wrong_parent"   => 0,
                "missing_parent" => 0,
            ],
            $data
        );

        $oddness = Category::countErrors('oddness');

        static::assertEquals(0, $oddness);
    }

    public function testIsBroken(): void
    {
        static::makeTree(null, 1, 3, 2, 1, 1);

        static::assertEquals(false, Category::isBroken());
    }

}
