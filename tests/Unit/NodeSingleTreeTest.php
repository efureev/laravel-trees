<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Exceptions\{DeleteRootException, Exception, NotSupportedException, UniqueRootException};
use Fureev\Trees\Tests\models\Category;

class NodeSingleTreeTest extends AbstractUnitTestCase
{
    protected static $modelClass = Category::class;

    public function testCreateRoot(): void
    {
        $model = static::createRoot();

        $this->assertSame(1, $model->id);

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
        $this->assertSame(1, $node21->levelValue());

        $_root = $node21->parent()->first();

        $root->refresh();
        $this->assertTrue($_root->isRoot());
        $this->assertTrue($root->equalTo($_root));

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        $this->assertSame(2, $node31->levelValue());


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
        $this->assertSame(2, $node31->levelValue());
    }

    public function testInsertBeforeNodeException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $this->expectException(UniqueRootException::class);
        $node21->insertBefore($root)->save();
    }

    public function testInsertBeforeNode(): void
    {
        $root = static::createRoot();
        static::assertSame(0, $root->levelValue());

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();
        static::assertSame(1, $node21->levelValue());

        $node22 = new static::$modelClass(['title' => 'child 2.2']);
        $node22->insertBefore($node21)->save();
        static::assertSame(1, $node22->levelValue());

        $this->assertCount(2, $root->children);

        $node21->refresh();
        $node22->refresh();
        $root->refresh();

        $this->assertTrue($root->equalTo($node21->parent));
        $this->assertTrue($root->equalTo($node22->parent));

        $this->assertEquals(1, $node21->levelValue());
        $this->assertEquals(1, $node22->levelValue());

        $this->assertTrue($node22->equalTo($node21->siblings()->get()->first()));
        $this->assertTrue($node21->equalTo($node22->siblings()->get()->first()));

        $this->assertTrue($node22->equalTo($node21->prev()->first()));
        $this->assertTrue($node21->equalTo($node22->next()->first()));
    }


    public function testInsertAfterNode(): void
    {
        $root = static::createRoot();

        $node22 = new static::$modelClass(['title' => 'child 2.2']);
        $node22->appendTo($root)->save();
        static::assertSame(1, $node22->levelValue());

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->insertAfter($node22)->save();
        static::assertSame(1, $node21->levelValue());

        $this->assertCount(2, $root->children);

        $node21->refresh();
        $node22->refresh();
        $root->refresh();

        $this->assertTrue($root->equalTo($node21->parent));
        $this->assertTrue($root->equalTo($node22->parent));

        $this->assertEquals(1, $node21->levelValue());
        $this->assertEquals(1, $node22->levelValue());

        $this->assertTrue($node22->equalTo($node21->siblings()->get()->first()));
        $this->assertTrue($node21->equalTo($node22->siblings()->get()->first()));

        $this->assertTrue($node22->equalTo($node21->prev()->first()));
        $this->assertTrue($node21->equalTo($node22->next()->first()));
    }

    public function testInsertAfterRootException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();
        $this->expectException(UniqueRootException::class);
        $node21->insertAfter($root)->save();
    }

    public function testInsertBeforeRootException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $this->expectException(UniqueRootException::class);
        $node21->insertBefore($root)->save();
    }

    public function testAppendToSameException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $this->expectException(Exception::class);
        $node21->appendTo($node21)->save();
    }

    public function testAppendToNonExistParentException(): void
    {
        $root   = new static::$modelClass(['title' => 'root']);
        $node21 = new static::$modelClass(['title' => 'child 2.1']);

        $this->expectException(Exception::class);
        $node21->appendTo($root)->save();
    }

    public function testPrependToSameException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $this->expectException(Exception::class);
        $node21->prependTo($node21)->save();
    }

    public function testMoveToSelfChildrenException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $node21->refresh();
        static::assertTrue($node31->isChildOf($node21));

        $this->expectException(Exception::class);
        $node21->appendTo($node31)->save();
    }


    public function testInsertAfterNodeException(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);

        $this->expectException(UniqueRootException::class);
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

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->levelValue());

        $root->refresh();
        $this->assertTrue($node21->isLeaf());
        $this->assertTrue($node21->isChildOf($root));

        $this->assertTrue($node21->delete());

        $root->refresh();
        $this->assertTrue($root->isLeaf());
        $this->assertEmpty($root->children()->count());

        $node41 = new static::$modelClass(['title' => 'child 4.1']);
        $node41->delete();
    }


    public function testDeleteChildrenNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->levelValue());

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->levelValue());

        $root->refresh();
        $node21->refresh();

        $this->assertFalse($node21->isLeaf());
        $this->assertTrue($node31->isLeaf());
        $this->assertTrue($node31->isChildOf($root));

        $this->assertTrue($node21->delete());
        $node31->refresh();

        static::assertSame(1, $node31->levelValue());

        $this->assertTrue($node31->isLeaf());
        $root->refresh();

        $this->assertTrue($root->equalTo($node31->parent));
    }

    public function testDeleteWithChildrenNode(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->levelValue());

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->levelValue());

        $node41 = new static::$modelClass(['title' => 'child 4.1']);
        $node41->prependTo($node31)->save();
        static::assertSame(3, $node41->levelValue());

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
        static::assertSame(1, $node31->levelValue());

        $node41 = new static::$modelClass(['title' => 'child 4.1 new ']);
        $node41->appendTo($node31)->save();
        static::assertSame(2, $node41->levelValue());

        $node51 = new static::$modelClass(['title' => 'child 5.1 new ']);
        $node51->prependTo($node41)->save();
        static::assertSame(3, $node51->levelValue());

        $root->refresh();
        $node51->refresh();
        $node31->refresh();

        $this->assertTrue($node51->isLeaf());
        $this->assertTrue($node51->isChildOf($root));
        $this->assertTrue($node51->isChildOf($node31));

        $this->assertEquals(1, $root->leftOffset());
        $this->assertEquals(8, $root->rightOffset());

        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->levelValue());
    }


    public function testMove(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        static::assertSame(1, $node21->levelValue());

        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();
        static::assertSame(2, $node31->levelValue());

        $node31->appendTo($root)->save();
        $node31->refresh();
        static::assertSame(1, $node31->levelValue());

        $this->assertTrue($root->equalTo($node31->parent));
        $this->assertCount(2, $root->children);

        $node31->appendTo($node21)->save();
        $node31->refresh();
        static::assertSame(2, $node31->levelValue());

        $node21->refresh();
        $root->refresh();

        $this->assertTrue($node21->equalTo($node31->parent));
        $this->assertCount(1, $root->children);
        $this->assertCount(1, $node21->children);
    }

    public function testUsesSoftDelete(): void
    {
        $model = new static::$modelClass(['id' => 1, 'title' => 'root node']);
        $this->assertFalse($model::isSoftDelete());
    }


    public function testGetBounds(): void
    {
        $model = static::createRoot();

        $this->assertIsArray($model->getBounds());
        $this->assertCount(4, $model->getBounds());
        $this->assertEquals(1, $model->getBounds()[0]);
        $this->assertEquals(2, $model->getBounds()[1]);
        $this->assertEquals(0, $model->getBounds()[2]);
        $this->assertEquals(null, $model->getBounds()[3]);
    }

    public function testGetNodeBounds(): void
    {
        $model = static::createRoot();

        $data_1 = $model->getNodeBounds($model);
        $data_2 = $model->getNodeBounds($model->getKey());
        $this->assertIsArray($data_1);
        $this->assertIsArray($data_2);
        $this->assertCount(4, $data_1);
        $this->assertEquals($data_2, $data_1);
    }

    public function testBaseSaveException(): void
    {
        $model = new static::$modelClass(['id' => 2, 'title' => 'node']);
        $this->expectException(NotSupportedException::class);
        $model->save();
    }

    public function testUp(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node41 = new static::$modelClass(['title' => 'child 4.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();

        $children = $root->children()->defaultOrder()->get()->map(
            function ($item) {
                return $item->title;
            }
        );

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->up());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(
            function ($item) {
                return $item->title;
            }
        );

        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());
        $node31->refresh();

        static::assertFalse($node31->up());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(
            function ($item) {
                return $item->title;
            }
        );

        static::assertEquals(['child 3.1', 'child 2.1', 'child 4.1'], $children->toArray());
    }

    public function testDown(): void
    {
        $root = static::createRoot();

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node41 = new static::$modelClass(['title' => 'child 4.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();

        $children = $root->children()->defaultOrder()->get()->map(
            function ($item) {
                return $item->title;
            }
        );

        static::assertCount(3, $children);
        static::assertEquals(['child 2.1', 'child 3.1', 'child 4.1'], $children->toArray());

        static::assertTrue($node31->down());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(
            function ($item) {
                return $item->title;
            }
        );

        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());

        $node31->refresh();
        static::assertFalse($node31->down());
        static::assertFalse($node31->isForceSaving());


        $children = $root->children()->defaultOrder()->get()->map(
            function ($item) {
                return $item->title;
            }
        );

        static::assertEquals(['child 2.1', 'child 4.1', 'child 3.1'], $children->toArray());
    }


    public function testDescendants(): void
    {
        $root = static::createRoot();

        $node21  = new static::$modelClass(['title' => 'child 2.1']);
        $node31  = new static::$modelClass(['title' => 'child 3.1']);
        $node41  = new static::$modelClass(['title' => 'child 4.1']);
        $node32  = new static::$modelClass(['title' => 'child 3.2']);
        $node321 = new static::$modelClass(['title' => 'child 3.2.1']);

        $node21->appendTo($root)->save();
        $node31->appendTo($root)->save();
        $node41->appendTo($root)->save();
        $node32->appendTo($node31)->save();
        $node321->appendTo($node32)->save();

        $root->refresh();

        $list = $root->descendantsNew();

        static::assertEquals(5, $list->count());
    }

    public function testGetNodeData(): void
    {
        $root = static::createRoot();

        $data = static::$modelClass::getNodeData($root->id);
        $this->assertEquals(['lft' => 1, 'rgt' => 2, 'lvl' => 0, 'parent_id' => null], $data);
    }

}
