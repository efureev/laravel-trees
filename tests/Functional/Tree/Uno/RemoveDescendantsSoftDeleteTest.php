<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\ArchivedCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * On a soft-deleting model the rows stay, and a trashed node keeps its place — so there is no
 * room to give back and nothing shifts. The same rule `afterDelete()` follows.
 */
class RemoveDescendantsSoftDeleteTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<ArchivedCategory>
     */
    protected static function modelClass(): string
    {
        return ArchivedCategory::class;
    }

    #[Test]
    public function theSpanIsKeptBecauseTheRowsAre(): void
    {
        /** @var ArchivedCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var ArchivedCategory $a */
        $a = static::model(['title' => 'a']);
        $a->appendTo($root->refresh())->save();

        /** @var ArchivedCategory $a1 */
        $a1 = static::model(['title' => 'a1']);
        $a1->appendTo($a->refresh())->save();

        $a = $a->refresh();

        $a->removeDescendants();

        // Trashed, not gone.
        static::assertSame(2, ArchivedCategory::query()->count());
        static::assertTrue(
            ArchivedCategory::withTrashed()->whereKey($a1->getKey())->first()->trashed()
        );

        // And still holding its bounds, so nothing moved.
        static::assertSame([2, 5], [$a->refresh()->leftValue(), $a->rightValue()]);
        static::assertSame([1, 6], [$root->refresh()->leftValue(), $root->rightValue()]);

        static::assertFalse((new HealthyChecker(ArchivedCategory::class))->isBroken());
    }
}
