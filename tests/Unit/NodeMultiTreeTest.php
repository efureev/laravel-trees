<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Config;
use Fureev\Trees\Exceptions\{DeleteRootException,
    Exception,
    NotSupportedException,
    TreeNeedValueException,
    UniqueRootException};
use Fureev\Trees\Migrate;
use Fureev\Trees\Tests\models\Page;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class NodeMultiTreeTest extends AbstractUnitTestCase
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

    public function testCreateRootMissingTree(): void
    {
        /** @var Page $model */
        $model = new self::$treeModel(['title' => 'root node']);
        $model->setTreeConfig(new Config(['treeAttribute' => 'tree_id', 'autoGenerateTreeId' => false]));

        $this->expectException(TreeNeedValueException::class);
        $model->makeRoot()->save();
    }

    public function testCreateRootAutoGenTree(): void
    {
        /** @var Page $model */
        $model = static::createRoot();

        $this->assertSame(1, $model->getKey());
        $this->assertSame(1, $model->getTree());
        $this->assertTrue($model->isRoot());

        $this->assertInstanceOf(self::$treeModel, $model->getRoot());

        $this->assertEquals($model->id, $model->getRoot()->id);
        $this->assertEquals($model->title, $model->getRoot()->title);
        $this->assertEquals($model->lvl, $model->getRoot()->lvl);
        $this->assertEmpty($model->parents());
        $this->assertEmpty($model->children);
    }

    private function createNodeAutoGen($no, ?Model $parent = null): Model
    {
        /** @var Page $model */
        $model = new self::$treeModel(['title' => ($parent ? 'sub' : 'root') . 'node #' . $no]);

        if (!$parent) {
            $model->makeRoot();
        } else {
            $model->prependTo($parent);
        }

        $model->save();

        $this->assertEmpty($model->children);
        $this->assertInstanceOf(self::$treeModel, $model->getRoot());
        if (!$parent) { //root
            $this->assertSame($no, $model->getTree());
            $this->assertTrue($model->isRoot());

            $this->assertEquals($model->id, $model->getRoot()->id);
            $this->assertEquals($model->title, $model->getRoot()->title);
            $this->assertEquals($model->lvl, $model->getRoot()->lvl);
            $this->assertEmpty($model->parents());
        } else { // sub-nodes
            $this->assertSame($model->getRoot()->getKey(), $model->parent->getKey());
            $this->assertSame($model->parent->getTree(), $model->getTree());
        }

        return $model;
    }

    public function testCreateRootAutoGenMultiTree(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $root = $this->createNodeAutoGen($i);

            for ($j = 1; $j <= 10; $j++) {
                $this->createNodeAutoGen($j, $root);
            }
        }
    }

    public function testInsertNode(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $root = static::createRoot();

            $node21 = new self::$treeModel(['title' => 'child 2.1']);
            $node21->prependTo($root)->save();

            static::assertSame(1, $node21->getLevel());

            $_root = $node21->parent()->first();

            $root->refresh();
            static::assertTrue($_root->isRoot());
            static::assertTrue($root->equalTo($_root));

            $node31 = new self::$treeModel(['title' => 'child 3.1']);
            $node31->prependTo($node21)->save();
            static::assertSame(2, $node31->getLevel());


            $_node21 = $node31->parent()->first();

            static::assertFalse($_node21->isRoot());
            $node21->refresh();
            static::assertTrue($node21->equalTo($_node21));

            $_root = $node31->getRoot();
            static::assertTrue($_root->isRoot());

            $root->refresh();
            static::assertTrue($root->equalTo($_root));

            $parents = $node31->parents();
            static::assertCount(2, $parents);
            static::assertSame(2, $node31->getLevel());
        }
    }

    public function testInsertBeforeNodeException(): void
    {
        $roots = static::createRoots();

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $this->expectException(UniqueRootException::class);
        $node21->insertBefore($roots[0])->save();
    }

    public function testInsertBeforeNode(): void
    {
        $roots = static::createRoots();
        [$root, $root2] = $roots;

        static::assertSame(0, $root->getLevel());
        static::assertSame(0, $root2->getLevel());

        /** @var Page $node21 */
        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node22 = new self::$treeModel(['title' => 'child 2.2']);
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
        $roots = static::createRoots();
        $root = $roots[0];

        $node22 = new self::$treeModel(['title' => 'child 2.2']);
        $node22->appendTo($root)->save();
        static::assertSame(1, $node22->getLevel());

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
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

    public function testInsertAfterRootException(): void
    {
        $root = static::createRoots();

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->appendTo($root[0])->save();
        $this->expectException(UniqueRootException::class);
        $node21->insertAfter($root[0])->save();
    }

    public function testInsertBeforeRootException(): void
    {
        $root = static::createRoots();

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->appendTo($root[0])->save();
        $this->expectException(UniqueRootException::class);
        $node21->insertBefore($root[0])->save();
    }

    public function testAppendToSameException(): void
    {
        $root = static::createRoots();

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->appendTo($root[0])->save();
        $this->expectException(Exception::class);
        $node21->appendTo($node21)->save();
    }

    public function testAppendToNonExistParentException(): void
    {
        $root = new self::$treeModel(['title' => 'root']);
        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $this->expectException(Exception::class);
        $node21->appendTo($root)->save();
    }

    public function testPrependToSameException(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();
        $this->expectException(Exception::class);
        $node21->prependTo($node21)->save();
    }

    public function testMoveToSelfChildrenException(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        /** @var Page $node31 */
        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();

        /** @var Page $node31 */
        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $node21->refresh();
        static::assertTrue($node31->isChildOf($node21));

        $this->expectException(Exception::class);
        $node21->appendTo($node31)->save();
    }

    public function testInsertAfterNodeException(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $this->expectException(UniqueRootException::class);
        $node21->insertAfter($root)->save();
    }


    public function testDeleteRootNode(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        $this->expectException(DeleteRootException::class);
        $root->delete();
    }

    public function testDeleteNode(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $root->refresh();
        $this->assertTrue($node21->isLeaf());
        $this->assertTrue($node21->isChildOf($root));

        $this->assertTrue($node21->delete());

        $root->refresh();
        $this->assertTrue($root->isLeaf());
        $this->assertEmpty($root->children()->count());

        $node41 = new self::$treeModel(['title' => 'child 4.1']);
        $node41->delete();
    }


    public function testDeleteChildrenNode(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node31 = new self::$treeModel(['title' => 'child 3.1']);
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
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->getLevel());

        $node41 = new self::$treeModel(['title' => 'child 4.1']);
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


        $node31 = new self::$treeModel(['title' => 'child 3.1 new']);
        $node31->appendTo($root)->save();
        static::assertSame(1, $node31->getLevel());

        $node41 = new self::$treeModel(['title' => 'child 4.1 new ']);
        $node41->appendTo($node31)->save();
        static::assertSame(2, $node41->getLevel());

        $node51 = new self::$treeModel(['title' => 'child 5.1 new ']);
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
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->getLevel());

        $node31 = new self::$treeModel(['title' => 'child 3.1']);
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
        $model = new self::$treeModel(['id' => 1, 'title' => 'root node']);
        $this->assertFalse($model::isSoftDelete());
    }


    public function testGetBounds(): void
    {
        $model = static::createRoot();

        $this->assertIsArray($model->getBounds());
        $this->assertCount(5, $model->getBounds());
        $this->assertEquals(1, $model->getBounds()[0]);
        $this->assertEquals(2, $model->getBounds()[1]);
        $this->assertEquals(0, $model->getBounds()[2]);
        $this->assertEquals(null, $model->getBounds()[3]);
        $this->assertEquals(1, $model->getBounds()[4]);
    }

    public function testGetNodeBounds(): void
    {
        $model = static::createRoot();

        $data_1 = $model->getNodeBounds($model);
        $data_2 = $model->getNodeBounds($model->getKey());
        $this->assertIsArray($data_1);
        $this->assertIsArray($data_2);
        $this->assertCount(5, $data_1);
        $this->assertEquals($data_2, $data_1);
    }

    public function testDescendants(): void
    {
        $roots = static::createRoots();

        /** @var Page $root3 */
        $root3 = $roots[3];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        $node41 = new self::$treeModel(['title' => 'child 4.1']);
        $node32 = new self::$treeModel(['title' => 'child 3.2']);
        $node321 = new self::$treeModel(['title' => 'child 3.2.1']);

        $node21->appendTo($root3)->save();
        $node31->appendTo($root3)->save();
        $node41->appendTo($root3)->save();
        $node32->appendTo($node31)->save();
        $node321->appendTo($node32)->save();

        $root3->refresh();

        // @todo: need benchmarks
        $listQ = $root3->descendantsNew();
        $list = $root3->descendants();

        static::assertEquals(5, $list->count());
        static::assertEquals(5, $listQ->count());
    }
/*
    public function testAncestors(): void
    {
        $roots = static::createRoots();

        /** @var Page $root3 * /
        $root3 = $roots[3];
        $root1 = $roots[1];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        $node41 = new self::$treeModel(['title' => 'child 4.1']);
        $node32 = new self::$treeModel(['title' => 'child 3.2']);
        /** @var Page $node321 * /
        $node321 = new self::$treeModel(['title' => 'child 3.2.1']);

        $node21->appendTo($root3)->save();
        $node31->appendTo($root3)->save();
        $node41->appendTo($root3)->save();
        $node32->appendTo($node31)->save();
        $node321->appendTo($node32)->save();

        $node32->refresh();
        $node31->refresh();
        $node41->refresh();

        (new self::$treeModel(['title' => 'child #1 - 2.1']))->appendTo($root1)->save();
        (new self::$treeModel(['title' => 'child #1 - 3.1']))->appendTo($root1)->save();
        (new self::$treeModel(['title' => 'child #1 - 4.1']))->appendTo($root1)->save();

        // @todo: need benchmarks
        static::assertEquals(3, $node321->ancestors()->count());
        static::assertEquals(3, $node321->parents()->count());


        static::assertEquals(2, $node32->ancestors()->count());
        static::assertEquals(2, $node32->parents()->count());

        static::assertEquals(1, $node31->ancestors()->count());
        static::assertEquals(1, $node31->parents()->count());

        static::assertEquals(1, $node41->ancestors()->count());
        static::assertEquals(1, $node41->parents()->count());

        static::assertEquals(1, $node21->ancestors()->count());
        static::assertEquals(1, $node21->parents()->count());


    }*/

    public function testBaseSaveException(): void
    {
        $model = new self::$treeModel(['id' => 2, 'title' => 'node']);
        $this->expectException(NotSupportedException::class);
        $model->save();
    }

    public function testUp(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        /** @var Page $node21 */
        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        /** @var Page $node31 */
        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        /** @var Page $node41 */
        $node41 = new self::$treeModel(['title' => 'child 4.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();

        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->title;
        });

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->up());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->title;
        });

        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());
        $node31->refresh();

        static::assertFalse($node31->up());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->title;
        });

        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());

    }

    public function testDown(): void
    {
        $roots = static::createRoots();
        $root = $roots[0];

        $node21 = new self::$treeModel(['title' => 'child 2.1']);
        $node31 = new self::$treeModel(['title' => 'child 3.1']);
        $node41 = new self::$treeModel(['title' => 'child 4.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();

        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->title;
        });

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->down());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->title;
        });

        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

        $node31->refresh();
        static::assertFalse($node31->down());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(function ($item) {
            return $item->title;
        });

        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

    }

    public function testGetNodeData(): void
    {
        $roots = static::createRoots();
        /** @var Page $root */
        foreach ($roots as $root) {
            $data = self::$treeModel::getNodeData($root->id);
            $this->assertEquals(['lft' => 1, 'rgt' => 2, 'lvl' => 0, 'parent_id' => null, 'tree_id' => $root->id], $data);
        }
    }

    public function testPopulateTree(): void
    {

    }

    /*    public function testLoadJson(): void
        {
            $dataFile = file_get_contents(__DIR__ . '/../data/' . class_basename(self::$treeModel) . '.json');
            $data = Json::decode($dataFile);

            $root = static::createRoot();
            dd(Config::isNode($root));
            dd(class_basename());
            $node21 = new self::$treeModel(['title' => 'child 2.1']);
            $node31 = new self::$treeModel(['title' => 'child 3.1']);
            $node41 = new self::$treeModel(['title' => 'child 4.1']);

            $node21->appendTo($root)->save();
            $node31->appendTo($root)->save();
            $node41->appendTo($root)->save();

            $children = $root->children()->defaultOrder()->get()->map(function ($item) {
                return $item->title;
            });

            static::assertCount(3, $children);
            static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

            static::assertTrue($node31->down());
            static::assertFalse($node31->isForceSaving());


            $children = $root->children()->defaultOrder()->get()->map(function ($item) {
                return $item->title;
            });

            static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

            $node31->refresh();
            static::assertFalse($node31->down());
            static::assertFalse($node31->isForceSaving());


            $children = $root->children()->defaultOrder()->get()->map(function ($item) {
                return $item->title;
            });

            static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

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

}
