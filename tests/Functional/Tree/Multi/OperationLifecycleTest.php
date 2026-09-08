<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * A pending operation must be consumed by exactly one save(). It used to survive a save that
 * found nothing dirty — the reset lives in afterInsert()/afterUpdate(), and those only fire
 * when a write actually happened — so the next, unrelated save executed it.
 */
class OperationLifecycleTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * @return int[]
     */
    private function boundsOf(MultiCategory $node): array
    {
        $node->refresh();

        return [
            $node->leftValue(),
            $node->rightValue(),
        ];
    }

    #[Test]
    public function makeRootOnAnExistingRootDoesNotArmTheNextSave(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        $tree = $root->refresh()->treeValue();

        // Already a root: nothing changes, so the save writes nothing.
        $root->makeRoot()->save();
        static::assertSame($tree, $root->refresh()->treeValue());

        // An unrelated save must not move the node into a freshly generated tree.
        $root->title = 'renamed';
        $root->save();

        static::assertSame($tree, $root->refresh()->treeValue());
        static::assertSame('renamed', $root->refresh()->title);
    }

    #[Test]
    public function appendToTheCurrentParentDoesNotArmTheNextSave(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var MultiCategory $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        $bounds = $this->boundsOf($child);

        // Re-appending to the parent it already sits under changes no attribute.
        $child->appendTo($root->refresh())->save();
        static::assertSame($bounds, $this->boundsOf($child));

        $child->title = 'renamed';
        $child->save();

        static::assertSame($bounds, $this->boundsOf($child));
        static::assertFalse((new HealthyChecker(MultiCategory::class))->isBroken());
    }
}
