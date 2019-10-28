<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Migrate;
use Fureev\Trees\Tests\models\Page;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class NodeBuilderMultiTreeTest extends AbstractUnitTestCase
{
    /** @var string */
    private static $treeModel = Page::class;

    private static $treeModelTable;

    public static function setUpBeforeClass(): void
    {
        /** @var Page $model */
        $model = new self::$treeModel;
        self::$treeModelTable = $model->getTable();

        $schema = Capsule::schema();

        $schema->dropIfExists(self::$treeModelTable);
        Capsule::disableQueryLog();

        $config = $model->getTreeConfig();
        $schema->create(self::$treeModelTable, static function (Blueprint $table) use ($config) {
            $table->increments('id');

            Migrate::getColumns($table, $config);
            $table->string('title');

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
        Capsule::table(self::$treeModelTable)->truncate();
    }

  /*  public function testWhereAncestorOf(): void
    {
        $roots = static::createRoots();
        $root1 = $roots[1];
        static::createTree($root1);
        static::createTree($roots[3]);
        static::createTree($roots[2]);


        $node51 = self::$treeModel::where(['title' => 'child 5.1', 'tree_id' => 2])->first();
        static::assertEquals('child 5.1', $node51->title);

        $list = self::$treeModel::whereAncestorOf($node51->getKey())->get();

        static::assertCount(4, $list);


        $root = $node51->getRoot();

        static::assertTrue($root->isRoot());

        $list = self::$treeModel::whereAncestorOf($root)->get();
        static::assertCount(0, $list);
    }*/

    /**
     * @param int|null $tree
     *
     * @return Page
     */
    private static function createRoot(?int $tree = null): Page
    {
        /** @var Page $model */
        $model = new self::$treeModel(['title' => 'root node']);
        $model->makeRoot($tree)->save();

        return $model;
    }

    private static function createRoots(int $count = 10): array
    {
        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $list[] = static::createRoot();
        }
        return $list;
    }

    private static function createTree($parent = null): void
    {
        $root = $parent ?? self::$treeModel::create(['name' => 'root', '_setRoot' => true]);

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        $node41 = new self::$treeModel(['title' => 'child 4.1']);
        $node41->prependTo($node31)->save();
        $node42 = new self::$treeModel(['title' => 'child 4.2']);
        $node42->appendTo($node31)->save();
        $node43 = new self::$treeModel(['title' => 'child 4.3']);
        $node43->appendTo($node31)->save();
        $node51 = new self::$treeModel(['title' => 'child 5.1']);
        $node51->prependTo($node41)->save();

        $node22 = new self::$treeModel(['title' => 'child 2.2']);
        $node22->appendTo($root)->save();
        $node32 = new self::$treeModel(['title' => 'child 3.2']);
        $node32->prependTo($node22)->save();
    }

}
