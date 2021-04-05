<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\QueryBuilder;
use Fureev\Trees\Tests\models\CategorySoftDelete;

class NodeSoftDeleteTest extends AbstractUnitTestCase
{
    protected static $modelClass = CategorySoftDelete::class;

    public function testCreateRoot(): void
    {
        $model = static::createRoot();

        static::assertSame($model->id, 1);

        $this->assertTrue($model->isRoot());

        $this->assertInstanceOf(static::$modelClass, $model->getRoot());

        $this->assertEquals($model->id, $model->getRoot()->id);
        $this->assertEquals($model->title, $model->getRoot()->title);
        $this->assertEquals($model->lvl, $model->getRoot()->lvl);

        $this->assertEmpty($model->parents());

        $this->expectException(UniqueRootException::class);
        static::$modelClass::create(['title' => 'root', '_setRoot' => true]);
    }


    public function testInsertNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $_root = $node21->parent()->first();

        $root->refresh();
        $this->assertTrue($_root->isRoot());
        $this->assertTrue($root->equalTo($_root));

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $_node21 = $node31->parent()->first();

        $this->assertFalse($_node21->isRoot());
        $node21->refresh();
        $this->assertTrue($node21->equalTo($_node21));

        $_root = $node31->getRoot();
        $this->assertTrue($_root->isRoot());

        $root->refresh();
        $this->assertTrue($root->equalTo($_root));

        $parents = $node31->parents();
        $this->assertCount(2, $parents);
    }


    public function testDeleteRootNode(): void
    {
        $root = static::createRoot();

        $root->delete();

        $this->assertTrue($root->trashed());
    }

    public function testSoftDeleteNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $root->refresh();
        $this->assertTrue($node21->isLeaf());
        $this->assertTrue($node21->isChildOf($root));

        $this->assertTrue($node21->delete());

        $root->refresh();
        $this->assertTrue($root->isLeaf());
        $this->assertEmpty($root->children()->count());
    }


    public function testDeleteChildrenNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $root->refresh();
        $node21->refresh();

        $this->assertFalse($node21->isLeaf());
        $this->assertTrue($node31->isLeaf());
        $this->assertTrue($node31->isChildOf($root));

        $this->assertTrue($node21->delete());
        $node31->refresh();

        $this->assertTrue($node31->isLeaf());
    }

    public function testDeleteWithChildrenNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $node41 = new static::$modelClass(['title' => 'child 4.1']);
        $node41->prependTo($node31)->save();

        $root->refresh();
        $node21->refresh();
        $node31->refresh();

        $this->assertFalse($node21->isLeaf());
        $this->assertFalse($node31->isLeaf());
        $this->assertTrue($node41->isLeaf());
        $this->assertTrue($node41->isChildOf($root));
        $this->assertTrue($node41->isChildOf($node21));
        $this->assertTrue($node41->isChildOf($node31));

        $delNode = $node21->deleteWithChildren();

        $this->assertEquals(3, $delNode);
        $root->refresh();
        $this->assertTrue($root->isLeaf());
        $this->assertEmpty($root->children()->count());

        $this->assertEquals(1, $root->leftOffset());
        $this->assertEquals(2, $root->rightOffset());


        $node31 = new static::$modelClass(['title' => 'child 3.1 new']);
        $node31->appendTo($root)->save();

        $node41 = new static::$modelClass(['title' => 'child 4.1 new ']);
        $node41->appendTo($node31)->save();

        $node51 = new static::$modelClass(['title' => 'child 5.1 new ']);
        $node51->prependTo($node41)->save();

        $root->refresh();
        $node51->refresh();
        $node31->refresh();

        $this->assertTrue($node51->isLeaf());
        $this->assertTrue($node51->isChildOf($root));
        $this->assertTrue($node51->isChildOf($node31));

        $this->assertEquals(1, $root->leftOffset());
        $this->assertEquals(8, $root->rightOffset());
    }

    /* public function testDeleteWithChildrenNodeAndRestore(): void
     {
         $root = static::createRoot();

         $node21 = new static::$modelClass(['title' => 'child 2.1']);
         $node21->prependTo($root)->save();

         $node31 = new static::$modelClass(['title' => 'child 3.1']);
         $node31->prependTo($node21)->save();

         $node41 = new static::$modelClass(['title' => 'child 4.1']);
         $node41->prependTo($node31)->save();

         $root->refresh();
         $node21->refresh();

         $node21->deleteWithChildren();
         $node21->refresh();

         $node21->restore();

     }*/

    public function testUsesSoftDelete(): void
    {
        $model = new static::$modelClass(['id' => 1, 'title' => 'root node']);
        $this->assertTrue($model::isSoftDelete());
        $this->assertTrue(static::$modelClass::isSoftDelete());
    }

    public function testNewNestedSetQuery(): void
    {
        $model = new static::$modelClass(['id' => 1, 'title' => 'root node']);

        $this->assertInstanceOf(QueryBuilder::class, $model->newNestedSetQuery());
    }


    public function testRestoreNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $node21->refresh();
        $root->refresh();

        $this->assertTrue($node31->isLeaf());

        $node31->delete();

        $node21->refresh();
        $root->refresh();

        $this->assertTrue($node21->isLeaf());

        $node31->restore();

        $node31->refresh();
        $node21->refresh();
        $root->refresh();

        $this->assertTrue($node31->isLeaf());
        $this->assertFalse($node21->isLeaf());
    }
}
