<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Healthy;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Healthy\OddnessCheck;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\TreeBuilder;
use Fureev\Trees\Tests\models\v5\Category;

class NodeBuilderHealthyTreeTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    public function testCountErrors(): void
    {
        $root = TreeBuilder::from(self::modelClass())->build(1, 3, 2, 1, 1);

        $checker = new HealthyChecker($root);
        $result = $checker->check();

        static::assertEquals(
            [
                "OddnessCheck" => 0,
                "DuplicatesCheck" => 0,
                "WrongParentCheck" => 0,
//                "missing_parent" => 0,
            ],
            $result
        );
    }

    public function testOddnessCheck(): void
    {
        $root = TreeBuilder::from(self::modelClass())->build(1, 3, 2, 1, 1);

        $checker = new OddnessCheck($root);
        $result = $checker->check();

        static::assertEquals(0, $result);
    }

    public function testIsBroken(): void
    {
        $root = TreeBuilder::from(self::modelClass())->build(1, 3, 2, 1, 1);

        $checker = new HealthyChecker($root);

        static::assertEquals(false, $checker->isBroken());
    }
}
