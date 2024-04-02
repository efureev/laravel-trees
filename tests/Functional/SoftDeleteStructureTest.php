<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Tests\Functional\Helpers\InstallMigration;
use Fureev\Trees\Tests\Functional\Helpers\StructureHelper;
use Fureev\Trees\Tests\models\SoftDeleteStructure;

/**
 * @deprecated
 */
class SoftDeleteStructureTest extends AbstractFunctionalTestCase
{
    use StructureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        (new InstallMigration(SoftDeleteStructure::class))->install();
    }

    public function createOnlyRootAndThenDeleteIt(): void
    {
        $root = $this->createSoftDeleteStructure();

        static::assertEquals([1, 2, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals(0, $root->levelValue());
        static::assertTrue($root->isRoot());
        static::assertTrue($root->isLeaf());
        static::assertTrue($root->isLevel(0));
        static::assertTrue($root->exists);

        $root->delete();
        $root->refresh();

        static::assertEquals([1, 2, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals(0, $root->levelValue());
        static::assertTrue($root->isRoot());
        static::assertTrue($root->isLeaf());
        static::assertTrue($root->isLevel(0));
        static::assertTrue($root->exists);
        static::assertTrue($root->trashed());

        static::assertEquals(0, SoftDeleteStructure::count());
        static::assertEquals(1, SoftDeleteStructure::withTrashed()->count());
    }


    public function createSoftDeleteStructures(): void
    {
        $root = $this->createSoftDeleteStructure();
        static::assertEquals([1, 2, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals(0, $root->levelValue());
        static::assertTrue($root->isRoot());

        $structure1 = $this->createSoftDeleteStructure($root);
        static::assertEquals(1, $structure1->levelValue());
        self::refreshModels($root, $structure1);
        static::assertEquals([1, 4, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());

        $structure2 = $this->createSoftDeleteStructure($root);
        static::assertEquals(1, $structure2->levelValue());
        self::refreshModels($root, $structure1, $structure2);
        static::assertEquals([1, 6, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([4, 5, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        $structure11 = $this->createSoftDeleteStructure($structure1);
        static::assertEquals(2, $structure11->levelValue());
        self::refreshModels($root, $structure1, $structure2);
        static::assertEquals([1, 8, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 5, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([6, 7, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());


        $structure12 = $this->createSoftDeleteStructure($structure1);
        static::assertEquals(2, $structure12->levelValue());
        self::refreshModels($root, $structure1, $structure2);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());


        self::refreshModels($root, $structure1, $structure2);

        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(5, SoftDeleteStructure::count());

        static::assertTrue($structure1->isChildOf($root));
        static::assertTrue($structure2->isChildOf($root));
        static::assertTrue($structure11->isChildOf($structure1));
        static::assertTrue($structure12->isChildOf($structure1));
        static::assertTrue($structure11->isChildOf($root));
        static::assertTrue($structure12->isChildOf($root));

        static::assertFalse($root->isLeaf());
        static::assertFalse($structure1->isLeaf());
        static::assertTrue($structure2->isLeaf());
        static::assertTrue($structure11->isLeaf());
        static::assertTrue($structure12->isLeaf());

        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());

        static::assertEquals(2, $root->children()->count());
        static::assertEquals(2, $structure1->children()->count());
        static::assertEquals(0, $structure2->children()->count());
    }

    public function moveInstance(): void
    {
        $root = $this->createSoftDeleteStructure();

        $structure1  = $this->createSoftDeleteStructure($root);
        $structure2  = $this->createSoftDeleteStructure($root);
        $structure11 = $this->createSoftDeleteStructure($structure1);
        $structure12 = $this->createSoftDeleteStructure($structure1);

        $structure11->appendTo($structure2)->save();

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 5, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([6, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());
        static::assertEquals([7, 8, 2, $structure2->id, $structure11->treeValue()], $structure11->getBounds());


        $structure12->appendTo($structure11)->save();
        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([4, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());
        static::assertEquals([5, 8, 2, $structure2->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([6, 7, 3, $structure11->id, $structure12->treeValue()], $structure12->getBounds());

        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(5, SoftDeleteStructure::count());
    }

    public function deleteNodes(): void
    {
        $root = $this->createSoftDeleteStructure();

        $structure1  = $this->createSoftDeleteStructure($root);
        $structure2  = $this->createSoftDeleteStructure($root);
        $structure11 = $this->createSoftDeleteStructure($structure1);
        $structure12 = $this->createSoftDeleteStructure($structure1);

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        $structure1->deleteWithChildren(false);


        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(2, SoftDeleteStructure::count());

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);

        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(2, SoftDeleteStructure::count());
        static::assertEquals(5, SoftDeleteStructure::withTrashed()->count());
    }


    public function deleteAndRestoreNodes(): void
    {
        $root = $this->createSoftDeleteStructure();

        $structure1  = $this->createSoftDeleteStructure($root);
        $structure2  = $this->createSoftDeleteStructure($root);
        $structure11 = $this->createSoftDeleteStructure($structure1);
        $structure12 = $this->createSoftDeleteStructure($structure1);

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        $structure1->deleteWithChildren(false);

        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(2, SoftDeleteStructure::count());

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);

        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        static::assertEquals(5, SoftDeleteStructure::withTrashed()->count());
        static::assertTrue($structure12->trashed());

        $structure12->restore();

        static::assertFalse($structure12->trashed());
        static::assertTrue($structure11->trashed());
        static::assertTrue($structure1->trashed());
        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(3, SoftDeleteStructure::count());

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);

        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        static::assertEquals(5, SoftDeleteStructure::withTrashed()->count());

        $structure1->restoreWithDescendants();

        self::refreshModels($structure1, $structure11, $structure12);

        static::assertFalse($structure12->trashed());
        static::assertFalse($structure11->trashed());
        static::assertFalse($structure1->trashed());

        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(5, SoftDeleteStructure::count());

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);

        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        static::assertEquals(5, SoftDeleteStructure::withTrashed()->count());
    }

    public function restoreParentsNodes(): void
    {
        $root = $this->createSoftDeleteStructure();

        $structure1  = $this->createSoftDeleteStructure($root);
        $structure2  = $this->createSoftDeleteStructure($root);
        $structure11 = $this->createSoftDeleteStructure($structure1);
        $structure12 = $this->createSoftDeleteStructure($structure1);

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);
        $structure1->deleteWithChildren(false);
        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);

        $structure11->restoreWithParents();

        self::refreshModels($structure1, $structure11, $structure12);

        static::assertTrue($structure12->trashed());
        static::assertFalse($structure11->trashed());
        static::assertFalse($structure1->trashed());
        static::assertFalse(SoftDeleteStructure::isBroken());
        static::assertEquals(4, SoftDeleteStructure::count());
    }

}
