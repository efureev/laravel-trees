<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs docs/Architecture.md: positioning methods record an intent and write nothing. The work
 * happens on the next save(), driven by the model events.
 */
class DeferredOperationTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Backs docs/Architecture.md: appendTo() issues no query and persists nothing on its own.
     */
    #[Test]
    public function positioningANodeIssuesNoQuery(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $node */
        $node = static::model(['title' => 'pending']);

        static::model()->getConnection()->flushQueryLog();

        $node->appendTo($root);

        static::assertSame([], static::model()->getConnection()->getQueryLog());
        static::assertFalse($node->exists);
        static::assertSame(1, Category::query()->count());
    }

    /**
     * Backs docs/Architecture.md: the same holds for an existing node — moving it is recorded,
     * and only save() carries it out.
     */
    #[Test]
    public function movingAnExistingNodeTakesEffectOnSave(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $first */
        $first = static::model(['title' => 'first']);
        $first->appendTo($root)->save();

        /** @var Category $second */
        $second = static::model(['title' => 'second']);
        $second->appendTo($root->refresh())->save();

        $boundsBefore = [
            $second->refresh()->leftValue(),
            $second->rightValue(),
        ];

        $second->appendTo($first->refresh());

        // Recorded, not applied: the database still holds the old position.
        static::assertSame(
            $boundsBefore,
            [
                Category::query()->find($second->getKey())->leftValue(),
                Category::query()->find($second->getKey())->rightValue(),
            ]
        );

        $second->save();

        static::assertTrue($second->refresh()->isChildOf($first->refresh()));
    }

    /**
     * Backs docs/Architecture.md: the tree configuration is built when the model is initialised,
     * so the casts for the tree columns are already in place on a brand new instance.
     */
    #[Test]
    public function treeCastsArePresentOnAFreshInstance(): void
    {
        $casts = static::model()->getCasts();

        $model = static::model();

        static::assertSame('integer', $casts[(string)$model->leftAttribute()]);
        static::assertSame('integer', $casts[(string)$model->rightAttribute()]);
        static::assertSame('integer', $casts[(string)$model->levelAttribute()]);
        static::assertArrayHasKey((string)$model->parentAttribute(), $casts);
    }
}
