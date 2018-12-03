<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\NestedSetConfig;
use Fureev\Trees\Tests\models\Category;
use Illuminate\Database\Capsule\Manager as Capsule;

class NodeTest extends AbstractUnitTestCase
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
//        Category::resetActionsPerformed();
        date_default_timezone_set('America/Denver');
    }

    public function tearDown()
    {
        Capsule::table('categories')->truncate();
    }

    /**
     * @throws \Php\Support\Exceptions\MissingClassException
     */
    public function testCreateRoot()
    {
        $model = static::createRoot();

        $this->assertTrue($model->id === 1);

        $this->assertTrue($model->isRoot());

        $this->assertInstanceOf(Category::class, $model->getRoot());

        $this->assertEquals($model->id, $model->getRoot()->id);
        $this->assertEquals($model->name, $model->getRoot()->name);
        $this->assertEquals($model->lvl, $model->getRoot()->lvl);

        //$this->assertEmpty($model->parents());

        $this->expectException(UniqueRootException::class);
        Category::create(['name' => 'root', '_setRoot' => true]);
    }


    public function testInsertNode()
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();

        $_root = $node21->parent()->first();

        $root->refresh();
        $this->assertTrue($_root->isRoot());
        $this->assertTrue($root->equalTo($_root));

        $node31 = new Category(['name' => 'child 3.1']);
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

    /**
     * @return \Fureev\Trees\Tests\models\Category
     */
    private static function createRoot()
    {
        $model = new Category(['id' => 1, 'name' => 'root node']);

        $model->makeRoot()->save();

        return $model;
    }

    /*
        public function testMakeRoot()
        {
    //        $model = new Category(['name' => 'root']);
    //        $model->save();


        }


        public function testParent()
        {
    //        parent()

        }

        public function testGetRoot()
        {
    //        Capsule::table('categories')->truncate();
        }*/
}
