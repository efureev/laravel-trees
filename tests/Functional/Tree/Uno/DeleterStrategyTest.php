<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\RecordedDeleteCategory;
use Fureev\Trees\Tests\models\v5\RecordingDeleter;
use PHPUnit\Framework\Attributes\Test;

/**
 * The other half of what `DeleteStrategyTest` covers. `setChildrenHandlerOnDelete()` replaces
 * what happens to the children of a node deleted normally; `setDeleterWithChildren()` replaces
 * what `deleteWithChildren()` runs. Only the first had coverage.
 */
class DeleterStrategyTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<RecordedDeleteCategory>
     */
    protected static function modelClass(): string
    {
        return RecordedDeleteCategory::class;
    }

    public function setUp(): void
    {
        parent::setUp();

        RecordingDeleter::$calls = [];
    }

    #[Test]
    public function aCustomDeleterReplacesTheDefault(): void
    {
        /** @var RecordedDeleteCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var RecordedDeleteCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var RecordedDeleteCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        $branch = $branch->refresh();
        $branch->deleteWithChildren();

        static::assertSame(
            [
                [
                    'key'   => $branch->getKey(),
                    'force' => true,
                ],
            ],
            RecordingDeleter::$calls
        );

        static::assertSame(
            ['root'],
            RecordedDeleteCategory::query()->defaultOrder()->get()->pluck('title')->all()
        );
    }

    /**
     * The flag reaches the strategy as given, so a soft delete can be told apart from a hard one.
     */
    #[Test]
    public function theForceFlagIsPassedThrough(): void
    {
        /** @var RecordedDeleteCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var RecordedDeleteCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var RecordedDeleteCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        $branch->refresh()->deleteWithChildren(false);

        static::assertFalse(RecordingDeleter::$calls[0]['force']);
    }
}
