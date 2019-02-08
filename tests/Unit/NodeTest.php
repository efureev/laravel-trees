<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Exceptions\{DeleteRootException, UniqueRootException};
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
        date_default_timezone_set('Europe/Moscow');
    }

    public function tearDown()
    {
        Capsule::table('categories')->truncate();
    }

    public function testCreateRoot(): void
    {
        $model = static::createRoot();

        $this->assertSame(1, $model->id);

        $this->assertTrue($model->isRoot());

        $this->assertInstanceOf(Category::class, $model->getRoot());

        $this->assertEquals($model->id, $model->getRoot()->id);
        $this->assertEquals($model->name, $model->getRoot()->name);
        $this->assertEquals($model->lvl, $model->getRoot()->lvl);

        $this->assertEmpty($model->parents());

        $this->expectException(UniqueRootException::class);
        Category::create(['name' => 'root', '_setRoot' => true]);
    }


    public function testInsertNode(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        $this->assertSame(1, $node21->getLevel());

        $_root = $node21->parent()->first();

        $root->refresh();
        $this->assertTrue($_root->isRoot());
        $this->assertTrue($root->equalTo($_root));

        $node31 = new Category(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        $this->assertSame(2, $node31->getLevel());


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
        $this->assertSame(2, $node31->getLevel());
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\UniqueRootException
     */
    public function testInsertBeforeNodeException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->insertBefore($root)->save();
    }

    public function testInsertBeforeNode(): void
    {
        $root = static::createRoot();
        static::assertSame(0, $root->getLevel());

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node22 = new Category(['name' => 'child 2.2']);
        $node22->insertBefore($node21)->save();
        static::assertSame(1, $node22->getLevel());

        $this->assertCount(2, $root->children);

        $node21->refresh();
        $node22->refresh();
        $root->refresh();

        $this->assertTrue($root->equalTo($node21->parent));
        $this->assertTrue($root->equalTo($node22->parent));

        $this->assertEquals(1, $node21->getLevel());
        $this->assertEquals(1, $node22->getLevel());

        $this->assertTrue($node22->equalTo($node21->siblings()->get()->first()));
        $this->assertTrue($node21->equalTo($node22->siblings()->get()->first()));

        $this->assertTrue($node22->equalTo($node21->prev()->first()));
        $this->assertTrue($node21->equalTo($node22->next()->first()));

    }


    public function testInsertAfterNode(): void
    {
        $root = static::createRoot();

        $node22 = new Category(['name' => 'child 2.2']);
        $node22->appendTo($root)->save();
        static::assertSame(1, $node22->getLevel());

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->insertAfter($node22)->save();
        static::assertSame(1, $node21->getLevel());

        $this->assertCount(2, $root->children);

        $node21->refresh();
        $node22->refresh();
        $root->refresh();

        $this->assertTrue($root->equalTo($node21->parent));
        $this->assertTrue($root->equalTo($node22->parent));

        $this->assertEquals(1, $node21->getLevel());
        $this->assertEquals(1, $node22->getLevel());

        $this->assertTrue($node22->equalTo($node21->siblings()->get()->first()));
        $this->assertTrue($node21->equalTo($node22->siblings()->get()->first()));

        $this->assertTrue($node22->equalTo($node21->prev()->first()));
        $this->assertTrue($node21->equalTo($node22->next()->first()));

    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\UniqueRootException
     */
    public function testInsertAfterRootException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $node21->insertAfter($root)->save();
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\UniqueRootException
     */
    public function testInsertBeforeRootException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $node21->insertBefore($root)->save();
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\Exception
     */
    public function testAppendToSameException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();
        $node21->appendTo($node21)->save();
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\Exception
     */
    public function testAppendToNonExistParentException(): void
    {
        $root = new Category(['name' => 'root']);
        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\Exception
     */
    public function testPrependToSameException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();
        $node21->prependTo($node21)->save();
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\Exception
     */
    public function testMoveToSelfChildrenException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $node31 = new Category(['name' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $node21->refresh();
        static::assertTrue($node31->isChildOf($node21));

        $node21->appendTo($node31)->save();
    }


    /**
     * @expectedException \Fureev\Trees\Exceptions\UniqueRootException
     */
    public function testInsertAfterNodeException(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->insertAfter($root)->save();
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

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $root->refresh();
        $this->assertTrue($node21->isLeaf());
        $this->assertTrue($node21->isChildOf($root));

        $this->assertTrue($node21->delete());

        $root->refresh();
        $this->assertTrue($root->isLeaf());
        $this->assertEmpty($root->children()->count());

        $node41 = new Category(['name' => 'child 4.1']);
        $node41->delete();
    }


    public function testDeleteChildrenNode(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node31 = new Category(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->getLevel());

        $root->refresh();
        $node21->refresh();

        $this->assertFalse($node21->isLeaf());
        $this->assertTrue($node31->isLeaf());
        $this->assertTrue($node31->isChildOf($root));

        $this->assertTrue($node21->delete());
        $node31->refresh();

        static::assertSame(1, $node31->getLevel());

        $this->assertTrue($node31->isLeaf());
    }

    public function testDeleteWithChildrenNode(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node31 = new Category(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->getLevel());

        $node41 = new Category(['name' => 'child 4.1']);
        $node41->prependTo($node31)->save();
        static::assertSame(3, $node41->getLevel());

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


        $node31 = new Category(['name' => 'child 3.1 new']);
        $node31->appendTo($root)->save();
        static::assertSame(1, $node31->getLevel());

        $node41 = new Category(['name' => 'child 4.1 new ']);
        $node41->appendTo($node31)->save();
        static::assertSame(2, $node41->getLevel());

        $node51 = new Category(['name' => 'child 5.1 new ']);
        $node51->prependTo($node41)->save();
        static::assertSame(3, $node51->getLevel());

        $root->refresh();
        $node51->refresh();
        $node31->refresh();

        $this->assertTrue($node51->isLeaf());
        $this->assertTrue($node51->isChildOf($root));
        $this->assertTrue($node51->isChildOf($node31));

        $this->assertEquals(1, $root->getLeftOffset());
        $this->assertEquals(8, $root->getRightOffset());

        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

    }


    public function testMove(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node31 = new Category(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->getLevel());

        $node31->appendTo($root)->save();
        $node31->refresh();
        static::assertSame(1, $node31->getLevel());

        $this->assertTrue($root->equalTo($node31->parent));
        $this->assertCount(2, $root->children);

        $node31->appendTo($node21)->save();
        $node31->refresh();
        static::assertSame(2, $node31->getLevel());

        $node21->refresh();
        $root->refresh();

        $this->assertTrue($node21->equalTo($node31->parent));
        $this->assertCount(1, $root->children);
        $this->assertCount(1, $node21->children);
    }

    public function testUsesSoftDelete(): void
    {
        $model = new Category(['id' => 1, 'name' => 'root node']);
        $this->assertFalse($model::isSoftDelete());
    }


    public function testGetBounds(): void
    {
        $model = static::createRoot();

        $this->assertIsArray($model->getBounds());
        $this->assertCount(2, $model->getBounds());
        $this->assertEquals(1, $model->getBounds()[0]);
        $this->assertEquals(2, $model->getBounds()[1]);
    }

    /**
     * @expectedException \Fureev\Trees\Exceptions\NotSupportedException
     */
    public function testBaseSaveException(): void
    {
        $model = new Category(['id' => 2, 'name' => 'node']);
        $model->save();
    }

    public function testUp(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node31 = new Category(['name' => 'child 3.1']);
        $node41 = new Category(['name' => 'child 4.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();

        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->up());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());
        $node31->refresh();

        static::assertFalse($node31->up());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());

    }

    public function testDown(): void
    {
        $root = static::createRoot();

        $node21 = new Category(['name' => 'child 2.1']);
        $node31 = new Category(['name' => 'child 3.1']);
        $node41 = new Category(['name' => 'child 4.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();

        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->down());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

        $node31->refresh();
        static::assertFalse($node31->down());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

    }

    /**
     * @return \Fureev\Trees\Tests\models\Category
     */
    private static function createRoot(): Category
    {
        $model = new Category(['id' => 1, 'name' => 'root node']);

        $model->makeRoot()->save();

        return $model;
    }
}
