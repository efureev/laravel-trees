<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\NestedSetConfig;
use Fureev\Trees\QueryBuilder;
use Fureev\Trees\Tests\models\CategorySoftDelete;
use Illuminate\Database\Capsule\Manager as Capsule;

class NodeSoftDeleteTest extends AbstractUnitTestCase
{
    public static function setUpBeforeClass()
    {
        $schema = Capsule::schema();

        $schema->dropIfExists('categories');
        Capsule::disableQueryLog();

        $schema->create('categories', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->softDeletes();
            NestedSetConfig::getColumns($table);
        });
        Capsule::enableQueryLog();
    }

    public function setUp()
    {
        //$data = include __DIR__.'/data/categories.php';
//        Capsule::table('categories')->insert($data);
        Capsule::flushQueryLog();
//        CategorySoftDelete::resetActionsPerformed();
        date_default_timezone_set('Europe/Moscow');
    }

    public function tearDown()
    {
        Capsule::table('categories')->truncate();
    }

    public function testCreateRoot(): void
    {
        $model = static::createRoot();

        $this->assertTrue($model->id === 1);

        $this->assertTrue($model->isRoot());

        $this->assertInstanceOf(CategorySoftDelete::class, $model->getRoot());

        $this->assertEquals($model->id, $model->getRoot()->id);
        $this->assertEquals($model->name, $model->getRoot()->name);
        $this->assertEquals($model->lvl, $model->getRoot()->lvl);

        $this->assertEmpty($model->parents());

        $this->expectException(UniqueRootException::class);
        CategorySoftDelete::create(['name' => 'root', '_setRoot' => true]);
    }


    public function testInsertNode(): void
    {
        $root = static::createRoot();

        $node21 = new CategorySoftDelete(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $_root = $node21->parent()->first();

        $root->refresh();
        $this->assertTrue($_root->isRoot());
        $this->assertTrue($root->equalTo($_root));

        $node31 = new CategorySoftDelete(['name' => 'child 3.1']);
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

        $this->expectException(DeleteRootException::class);
        $root->delete();
    }

    public function testDeleteNode(): void
    {
        $root = static::createRoot();

        $node21 = new CategorySoftDelete(['name' => 'child 2.1']);
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

        $node21 = new CategorySoftDelete(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $node31 = new CategorySoftDelete(['name' => 'child 3.1']);
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

        $node21 = new CategorySoftDelete(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $node31 = new CategorySoftDelete(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $node41 = new CategorySoftDelete(['name' => 'child 4.1']);
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

        $this->assertEquals(1, $root->getLeftOffset());
        $this->assertEquals(2, $root->getRightOffset());


        $node31 = new CategorySoftDelete(['name' => 'child 3.1 new']);
        $node31->appendTo($root)->save();

        $node41 = new CategorySoftDelete(['name' => 'child 4.1 new ']);
        $node41->appendTo($node31)->save();

        $node51 = new CategorySoftDelete(['name' => 'child 5.1 new ']);
        $node51->prependTo($node41)->save();

        $root->refresh();
        $node51->refresh();
        $node31->refresh();

        $this->assertTrue($node51->isLeaf());
        $this->assertTrue($node51->isChildOf($root));
        $this->assertTrue($node51->isChildOf($node31));

        $this->assertEquals(1, $root->getLeftOffset());
        $this->assertEquals(8, $root->getRightOffset());

    }

    /* public function testDeleteWithChildrenNodeAndRestore(): void
     {
         $root = static::createRoot();

         $node21 = new CategorySoftDelete(['name' => 'child 2.1']);
         $node21->prependTo($root)->save();

         $node31 = new CategorySoftDelete(['name' => 'child 3.1']);
         $node31->prependTo($node21)->save();

         $node41 = new CategorySoftDelete(['name' => 'child 4.1']);
         $node41->prependTo($node31)->save();

         $root->refresh();
         $node21->refresh();

         $node21->deleteWithChildren();
         $node21->refresh();

         $node21->restore();

     }*/

    public function testUsesSoftDelete(): void
    {
        $model = new CategorySoftDelete(['id' => 1, 'name' => 'root node']);
        $this->assertTrue($model::isSoftDelete());
        $this->assertTrue(CategorySoftDelete::isSoftDelete());
    }

    public function testNewNestedSetQuery(): void
    {
        $model = new CategorySoftDelete(['id' => 1, 'name' => 'root node']);

        $this->assertInstanceOf(QueryBuilder::class, $model->newNestedSetQuery());
    }

    /**
     * @return \Fureev\Trees\Tests\models\CategorySoftDelete
     */
    private static function createRoot(): CategorySoftDelete
    {
        $model = new CategorySoftDelete(['id' => 1, 'name' => 'root node']);

        $model->makeRoot()->save();

        return $model;
    }
}
