<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Tests\Functional\Helpers\InstallMigration;
use Fureev\Trees\Tests\Functional\Helpers\StructureHelper;
use Fureev\Trees\Tests\models\Structure;

class WithoutSoftDeleteStructureTest extends AbstractFunctionalTestCase
{
    use StructureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        (new InstallMigration(Structure::class))->install();
    }

    /**
     * @test
     */
    public function createOnlyRootAndThenDeleteIt(): void
    {
        $root = $this->createStructure();

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
        static::assertFalse($root->exists);
    }


    /**
     * @test
     */
    public function createStructures(): void
    {
        $root = $this->createStructure();
        static::assertEquals([1, 2, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals(0, $root->levelValue());
        static::assertTrue($root->isRoot());

        $structure1 = $this->createStructure($root);
        static::assertEquals(1, $structure1->levelValue());
        self::refreshModels($root, $structure1);
        static::assertEquals([1, 4, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());

        $structure2 = $this->createStructure($root);
        static::assertEquals(1, $structure2->levelValue());
        self::refreshModels($root, $structure1, $structure2);
        static::assertEquals([1, 6, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([4, 5, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        $structure11 = $this->createStructure($structure1);
        static::assertEquals(2, $structure11->levelValue());
        self::refreshModels($root, $structure1, $structure2);
        static::assertEquals([1, 8, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 5, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([6, 7, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());


        $structure12 = $this->createStructure($structure1);
        static::assertEquals(2, $structure12->levelValue());
        self::refreshModels($root, $structure1, $structure2);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());


        self::refreshModels($root, $structure1, $structure2);

        static::assertFalse(Structure::isBroken());
        static::assertEquals(5, Structure::count());

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

    /**
     * @test
     */
    public function moveInstance(): void
    {
        $root = $this->createStructure();

        $structure1  = $this->createStructure($root);
        $structure2  = $this->createStructure($root);
        $structure11 = $this->createStructure($structure1);
        $structure12 = $this->createStructure($structure1);

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

        static::assertFalse(Structure::isBroken());
        static::assertEquals(5, Structure::count());
    }

    /**
     * @test
     */
    public function deleteNodes(): void
    {
        $root = $this->createStructure();

        $structure1  = $this->createStructure($root);
        $structure2  = $this->createStructure($root);
        $structure11 = $this->createStructure($structure1);
        $structure12 = $this->createStructure($structure1);

        self::refreshModels($root, $structure1, $structure2, $structure11, $structure12);
        static::assertEquals([1, 10, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 7, 1, $root->id, $structure1->treeValue()], $structure1->getBounds());
        static::assertEquals([3, 4, 2, $structure1->id, $structure11->treeValue()], $structure11->getBounds());
        static::assertEquals([5, 6, 2, $structure1->id, $structure12->treeValue()], $structure12->getBounds());
        static::assertEquals([8, 9, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());

        $structure1->deleteWithChildren();

        static::assertFalse(Structure::isBroken());
        static::assertEquals(2, Structure::count());

        self::refreshModels($root, $structure2);

        static::assertEquals([1, 4, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $structure2->treeValue()], $structure2->getBounds());
    }

}
