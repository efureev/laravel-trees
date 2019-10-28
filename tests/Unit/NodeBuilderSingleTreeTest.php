<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Config;
use Fureev\Trees\Migrate;
use Fureev\Trees\Tests\models\Category;
use Illuminate\Database\Capsule\Manager as Capsule;

class NodeBuilderSingleTreeTest extends AbstractUnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        $schema = Capsule::schema();

        $schema->dropIfExists('categories');
        Capsule::disableQueryLog();

        $schema->create('categories', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->softDeletes();
            Migrate::getColumns($table, new Config());
        });
        Capsule::enableQueryLog();
    }

    public function setUp(): void
    {
        Capsule::flushQueryLog();
        date_default_timezone_set('Europe/Moscow');
    }

    public function tearDown(): void
    {
        Capsule::table('categories')->truncate();
    }

    public function testRoot(): void
    {
        $root = Category::create(['name' => 'root', '_setRoot' => true]);

        $node = Category::root()->first();

        static::assertTrue($node->equalTo($root));
    }

    public function testNotRoot(): void
    {
        $root = Category::create(['name' => 'root', '_setRoot' => true]);

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        $node31 = new Category(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $nodes = Category::notRoot()->get();

        static::assertCount(2, $nodes);

        $node = Category::notRoot()->where('name', 'child 3.1')->first();

        static::assertTrue($node->equalTo($node31));
    }


    public function testParents(): void
    {
        static::createTree();

        $node51 = Category::where(['name' => 'child 5.1'])->first();
        $parents = $node51->parents()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(4, $parents);
        static::assertEquals([
            "root",
            "child 2.1",
            "child 3.1",
            "child 4.1",
        ], $parents->toArray());


        $parents = $node51->parents(2)->map(function ($item) {
            return $item->name;
        });


        static::assertCount(2, $parents);
        static::assertEquals([
            'child 3.1',
            'child 4.1',
        ], $parents->toArray());
    }


    public function testSiblings(): void
    {
        static::createTree();

        $node42 = Category::where(['name' => 'child 4.2'])->first();

        $nodes = $node42->siblings()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });


        static::assertCount(2, $nodes);
        static::assertEquals([
            'child 4.1',
            'child 4.3',
        ], $nodes->toArray());


        $nodes = $node42->siblingsAndSelf()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });


        static::assertCount(3, $nodes);
        static::assertEquals([
            'child 4.1',
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());

    }


    public function testPrev(): void
    {
        static::createTree();

        $node41 = Category::where(['name' => 'child 4.1'])->first();
        $node42 = Category::where(['name' => 'child 4.2'])->first();
        $node43 = Category::where(['name' => 'child 4.3'])->first();

        $node = $node43->prev()->first();
        static::assertTrue($node42->equalTo($node));

        $node = $node->prev()->first();
        static::assertTrue($node41->equalTo($node));
    }

    public function testNext(): void
    {
        static::createTree();

        $node41 = Category::where(['name' => 'child 4.1'])->first();
        $node42 = Category::where(['name' => 'child 4.2'])->first();
        $node43 = Category::where(['name' => 'child 4.3'])->first();

        $node = $node41->next()->first();
        static::assertTrue($node42->equalTo($node));

        $node = $node->next()->first();
        static::assertTrue($node43->equalTo($node));
    }

    public function testPrevSiblings(): void
    {
        static::createTree();

        $node41 = Category::where(['name' => 'child 4.1'])->first();
        $node42 = Category::where(['name' => 'child 4.2'])->first();
        $node43 = Category::where(['name' => 'child 4.3'])->first();

        $nodes = $node42->prevSiblings()->get();
        static::assertCount(1, $nodes);

        static::assertTrue($node41->equalTo($nodes->first()));

        $nodes = $node43->prevSiblings()->defaultOrder()->get();
        static::assertCount(2, $nodes);

        static::assertTrue($node41->equalTo($nodes->first()));
        static::assertTrue($node42->equalTo($nodes->last()));

    }

    public function testNextSiblings(): void
    {
        static::createTree();

        $node41 = Category::where(['name' => 'child 4.1'])->first();
        $node42 = Category::where(['name' => 'child 4.2'])->first();
        $node43 = Category::where(['name' => 'child 4.3'])->first();

        $nodes = $node42->nextSiblings()->get();
        static::assertCount(1, $nodes);

        static::assertTrue($node43->equalTo($nodes->first()));

        $nodes = $node41->nextSiblings()->defaultOrder()->get();
        static::assertCount(2, $nodes);

        static::assertTrue($node42->equalTo($nodes->first()));
        static::assertTrue($node43->equalTo($nodes->last()));

    }

    public function testNextSibling(): void
    {
        static::createTree();

        $node42 = Category::where(['name' => 'child 4.2'])->first();
        $node43 = Category::where(['name' => 'child 4.3'])->first();

        $node = $node42->nextSibling()->first();
        static::assertTrue($node43->equalTo($node));
        static::assertNull($node->nextSibling()->first());

    }

    public function testPrevSibling(): void
    {
        static::createTree();

        $node41 = Category::where(['name' => 'child 4.1'])->first();
        $node42 = Category::where(['name' => 'child 4.2'])->first();

        $node = $node42->prevSibling()->first();
        static::assertTrue($node41->equalTo($node));
        static::assertNull($node->prevSibling()->first());

    }

    public function testLeaf(): void
    {
        static::createTree();

        $node31 = Category::where(['name' => 'child 3.1'])->first();


        $nodes = $node31->descendants()->leaf()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(3, $nodes);
        static::assertEquals([
            'child 5.1',
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());
    }


    public function testLeaves(): void
    {
        static::createTree();

        $node31 = Category::where(['name' => 'child 3.1'])->first();


        $nodes = $node31->descendants()->leaves()->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(3, $nodes);
        static::assertEquals([
            'child 5.1',
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());

        $nodes = $node31->descendants()->leaves(1)->defaultOrder()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(2, $nodes);
        static::assertEquals([
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());
    }

    public function testDescendants(): void
    {
        static::createTree();

        $node21 = Category::where(['name' => 'child 2.1'])->first();

        $nodes = $node21->descendants()->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(5, $nodes);
        static::assertEquals([
            'child 3.1',
            'child 4.1',
            'child 5.1',
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());


        $nodes = $node21->descendants(2)->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(4, $nodes);
        static::assertEquals([
            'child 3.1',
            'child 4.1',
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());


        $nodes = $node21->descendants(1)->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(1, $nodes);
        static::assertEquals([
            'child 3.1',
        ], $nodes->toArray());


        $nodes = $node21->descendants(2, true)->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(5, $nodes);
        static::assertEquals([
            'child 2.1',
            'child 3.1',
            'child 4.1',
            'child 4.2',
            'child 4.3',
        ], $nodes->toArray());


        $nodes = $node21->descendants(2, true, true)->get()->map(function ($item) {
            return $item->name;
        });

        static::assertCount(5, $nodes);
        static::assertEquals([
            'child 2.1',
            'child 3.1',
            'child 4.3',
            'child 4.2',
            'child 4.1',
        ], $nodes->toArray());

    }


    public function testWhereDescendantOf(): void
    {
        static::createTree();

        $node21 = Category::where(['name' => 'child 2.1'])->first();
        static::assertEquals('child 2.1', $node21->name);

        $list = Category::whereDescendantOf($node21->getKey())->get();
        static::assertCount(5, $list);


        $root = $node21->getRoot();

        static::assertTrue($root->isRoot());

        $list = Category::whereDescendantOf($root)->get();
        static::assertCount(8, $list);
    }


    private static function createTree(): void
    {
        $root = Category::create(['name' => 'root', '_setRoot' => true]);

        $node21 = new Category(['name' => 'child 2.1']);
        $node21->prependTo($root)->save();
        $node31 = new Category(['name' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        $node41 = new Category(['name' => 'child 4.1']);
        $node41->prependTo($node31)->save();
        $node42 = new Category(['name' => 'child 4.2']);
        $node42->appendTo($node31)->save();
        $node43 = new Category(['name' => 'child 4.3']);
        $node43->appendTo($node31)->save();
        $node51 = new Category(['name' => 'child 5.1']);
        $node51->prependTo($node41)->save();

        $node22 = new Category(['name' => 'child 2.2']);
        $node22->appendTo($root)->save();
        $node32 = new Category(['name' => 'child 3.2']);
        $node32->prependTo($node22)->save();
    }


}
