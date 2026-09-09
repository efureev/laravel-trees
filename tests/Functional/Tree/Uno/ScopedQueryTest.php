<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\ScopedCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * `getScopeAttributes()` returns an empty list in the package, so `applyNestedSetScope()` always
 * returned early. A model can return column names from it, and every query the package builds
 * then carries them.
 */
class ScopedQueryTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<ScopedCategory>
     */
    protected static function modelClass(): string
    {
        return ScopedCategory::class;
    }

    #[Test]
    public function theScopeAttributeReachesTheQuery(): void
    {
        /** @var ScopedCategory $root */
        $root       = static::model(['title' => 'root']);
        $root->path = ['tenant-a'];
        $root->makeRoot()->save();

        $sql = $root->refresh()->newNestedSetQuery()->toSql();

        static::assertStringContainsString('"scoped_categories"."path"', $sql);
    }

    #[Test]
    public function theTableCanBeOverriddenForTheScope(): void
    {
        /** @var ScopedCategory $root */
        $root       = static::model(['title' => 'root']);
        $root->path = ['tenant-a'];
        $root->makeRoot()->save();

        $sql = $root->refresh()->newNestedSetQuery('aliased')->toSql();

        static::assertStringContainsString('"aliased"."path"', $sql);
    }
}
