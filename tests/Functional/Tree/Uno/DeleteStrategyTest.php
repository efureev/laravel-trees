<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\StrictCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs docs/ManagingNodes.md: what happens to the children of a deleted node is a strategy,
 * swapped on the builder, not a fixed rule.
 */
class DeleteStrategyTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<StrictCategory>
     */
    protected static function modelClass(): string
    {
        return StrictCategory::class;
    }

    /**
     * Backs docs/ManagingNodes.md: setChildrenHandlerOnDelete() replaces the default
     * MoveChildrenToParent, so this model refuses the delete rather than re-parenting.
     */
    #[Test]
    public function aCustomChildrenHandlerReplacesTheDefault(): void
    {
        /** @var StrictCategory $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var StrictCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root)->save();

        /** @var StrictCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        // A leaf still deletes normally — the handler only runs for a node with children.
        $leaf->refresh()->delete();
        static::assertSame(2, StrictCategory::query()->count());

        $this->expectException(DeletedNodeHasChildrenException::class);

        /** @var StrictCategory $another */
        $another = static::model(['title' => 'another leaf']);
        $another->appendTo($branch->refresh())->save();

        $branch->refresh()->delete();
    }
}
