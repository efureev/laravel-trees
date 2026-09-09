<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * `isMulti()` reports how the model is configured. That is a property of the class — its tree
 * builder is static — so it cannot depend on the node a pending operation happens to target.
 */
class IsMultiTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function aPendingOperationDoesNotChangeTheAnswer(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        /** @var MultiCategory $child */
        $child = static::model(['title' => 'child']);

        static::assertTrue($child->isMulti());

        $child->appendTo($root->refresh());

        static::assertTrue($child->isMulti());
    }

    /**
     * The one case where answering about the target rather than about the model itself would
     * show: two classes over the same table, configured differently. `Category` has no tree
     * column, so it stays single whatever it is being appended to.
     */
    #[Test]
    public function theAnswerIsAboutTheModelNotAboutItsTarget(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        $single = new Category();
        $single->appendTo($root->refresh());

        static::assertFalse($single->isMulti());
        static::assertTrue($root->isMulti());
    }
}
