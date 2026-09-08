<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class ShiftTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function shiftKeepsOtherTreesUntouched(): void
    {
        /** @var MultiCategory $rootOne */
        $rootOne = static::model(['title' => 'tree 1']);
        $rootOne->makeRoot()->save();

        /** @var MultiCategory $childOne */
        $childOne = static::model(['title' => 'tree 1 child']);
        $childOne->appendTo($rootOne)->save();

        /** @var MultiCategory $rootTwo */
        $rootTwo = static::model(['title' => 'tree 2']);
        $rootTwo->makeRoot()->save();

        /** @var MultiCategory $childTwo */
        $childTwo = static::model(['title' => 'tree 2 child']);
        $childTwo->appendTo($rootTwo)->save();

        $twoBefore = [
            $rootTwo->refresh()->rightValue(),
            $childTwo->refresh()->leftValue(),
        ];

        // Grow tree 1: tree 2 shares the very same bounds and must not move.
        /** @var MultiCategory $extra */
        $extra = static::model(['title' => 'tree 1 extra']);
        $extra->appendTo($rootOne->refresh())->save();

        static::assertSame(6, $rootOne->refresh()->rightValue());
        static::assertSame(
            $twoBefore,
            [
                $rootTwo->refresh()->rightValue(),
                $childTwo->refresh()->leftValue(),
            ]
        );
    }
}
