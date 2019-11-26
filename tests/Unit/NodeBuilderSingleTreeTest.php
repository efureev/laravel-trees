<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\models\Category;

class NodeBuilderSingleTreeTest extends AbstractUnitTestCase
{
    protected static $modelClass = Category::class;

    public function testRoot(): void
    {
        $root = static::$modelClass::create(['title' => 'root', '_setRoot' => true]);

        $node = static::$modelClass::root()->first();

        static::assertTrue($node->equalTo($root));
    }

    public function testNotRoot(): void
    {
        $root = static::$modelClass::create(['title' => 'root', '_setRoot' => true]);

        $node21 = new static::$modelClass(['title' => 'child 2.1']);
        $node21->prependTo($root)->save();
        $node31 = new static::$modelClass(['title' => 'child 3.1']);
        $node31->prependTo($node21)->save();

        $nodes = static::$modelClass::notRoot()->get();

        static::assertCount(2, $nodes);

        $node = static::$modelClass::notRoot()->where('title', 'child 3.1')->first();

        static::assertTrue($node->equalTo($node31));
    }


    public function testParents(): void
    {
        static::makeTree(null, 1, 3, 2, 1, 1);

        $node12211 = static::$modelClass::where(['title' => 'child 1.2.2.1.1'])->first();
        $parents = $node12211->parents()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(4, $parents);
        static::assertEquals([
            'Root node 1',
            'child 1.2',
            'child 1.2.2',
            'child 1.2.2.1',
        ], $parents->toArray());


        $parents = $node12211->parents(2)->map(static function ($item) {
            return $item->title;
        });


        static::assertCount(2, $parents);
        static::assertEquals([
            'child 1.2.2',
            'child 1.2.2.1',
        ], $parents->toArray());
    }


    public function testSiblings(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node122 = static::$modelClass::where(['title' => 'child 1.2.2'])->first();

        $nodes = $node122->siblings()->defaultOrder()->get()->map(static function ($item) {
            return $item->title;
        });


        static::assertCount(3, $nodes);
        static::assertEquals([
            'child 1.2.4',
            'child 1.2.3',
            'child 1.2.1',
        ], $nodes->toArray());


        $nodes = $node122->siblingsAndSelf()->defaultOrder()->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(4, $nodes);
        static::assertEquals([
            'child 1.2.4',
            'child 1.2.3',
            'child 1.2.2',
            'child 1.2.1',
        ], $nodes->toArray());

    }

    public function testPrev(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node2 = static::$modelClass::where(['title' => 'child 1.2.2'])->first();
        $node3 = static::$modelClass::where(['title' => 'child 1.2.1'])->first();
        $node1 = static::$modelClass::where(['title' => 'child 1.2.3'])->first();

        static::assertTrue($node1->equalTo($node2->prev()->first()));
        static::assertTrue($node2->equalTo($node3->prev()->first()));
    }

    public function testNext(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node2 = static::$modelClass::where(['title' => 'child 1.2.2'])->first();
        $node3 = static::$modelClass::where(['title' => 'child 1.2.1'])->first();
        $node1 = static::$modelClass::where(['title' => 'child 1.2.3'])->first();

        static::assertTrue($node2->equalTo($node1->next()->first()));
        static::assertTrue($node3->equalTo($node2->next()->first()));
    }


    public function testPrevSiblings(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node3 = static::$modelClass::where(['title' => 'child 1.2.1'])->first();
        $node2 = static::$modelClass::where(['title' => 'child 1.2.2'])->first();
        $node1 = static::$modelClass::where(['title' => 'child 1.2.3'])->first();
        $node0 = static::$modelClass::where(['title' => 'child 1.2.4'])->first();

        static::assertCount(3, $node3->prevSiblings()->get());
        static::assertCount(2, $node2->prevSiblings()->get());
        static::assertTrue($node0->equalTo($node2->prevSiblings()->get()->first()));
        static::assertTrue($node1->equalTo($node2->prevSiblings()->get()->last()));
        static::assertCount(1, $node1->prevSiblings()->get());
        static::assertCount(0, $node0->prevSiblings()->get());

        $nodes = $node3->prevSiblings()->defaultOrder()->get();
        static::assertCount(3, $nodes);
        static::assertTrue($node0->equalTo($nodes->first()));
        static::assertTrue($node2->equalTo($nodes->last()));
    }


    public function testNextSiblings(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node3 = static::$modelClass::where(['title' => 'child 1.2.1'])->first();
        $node2 = static::$modelClass::where(['title' => 'child 1.2.2'])->first();
        $node1 = static::$modelClass::where(['title' => 'child 1.2.3'])->first();
        $node0 = static::$modelClass::where(['title' => 'child 1.2.4'])->first();

        static::assertCount(0, $node3->nextSiblings()->get());
        static::assertCount(1, $node2->nextSiblings()->get());

        static::assertTrue($node3->equalTo($node2->nextSiblings()->get()->first()));
        static::assertTrue($node3->equalTo($node2->nextSiblings()->get()->last()));
        static::assertCount(2, $node1->nextSiblings()->get());
        static::assertCount(3, $node0->nextSiblings()->get());

        $nodes = $node2->nextSiblings()->defaultOrder()->get();
        static::assertCount(1, $nodes);
        static::assertTrue($node3->equalTo($nodes->first()));
        static::assertTrue($node3->equalTo($nodes->last()));
    }

    public function testNextSibling(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node1 = static::$modelClass::where(['title' => 'child 1.2.3'])->first();
        $node0 = static::$modelClass::where(['title' => 'child 1.2.4'])->first();

        static::assertTrue($node1->equalTo($node0->nextSibling()->first()));
        static::assertNull($node1->nextSibling()->first()->nextSibling()->first()->nextSibling()->first());
    }

    public function testPrevSibling(): void
    {
        static::makeTree(null, 1, 3, 4);

        $node1 = static::$modelClass::where(['title' => 'child 1.2.3'])->first();
        $node0 = static::$modelClass::where(['title' => 'child 1.2.4'])->first();

        static::assertTrue($node0->equalTo($node1->prevSibling()->first()));
        static::assertNull($node0->prevSibling()->first());
    }

    public function testLeaf(): void
    {
        static::makeTree(null, 1, 3, 4);
        $node = static::$modelClass::where(['title' => 'child 1.2'])->first();

        $nodes = $node->descendants()->leaf()->defaultOrder()->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(4, $nodes);
        static::assertEquals([
            'child 1.2.4',
            'child 1.2.3',
            'child 1.2.2',
            'child 1.2.1',
        ], $nodes->toArray());
    }


    public function testLeaves(): void
    {
        static::makeTree(null, 1, 3, 4, 1);
        $node = static::$modelClass::where(['title' => 'child 1.3'])->first();

        $nodes = $node->descendants()->leaves()->defaultOrder()->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(4, $nodes);
        static::assertEquals([
            'child 1.3.4.1',
            'child 1.3.3.1',
            'child 1.3.2.1',
            'child 1.3.1.1',
        ], $nodes->toArray());

        $nodes = $node->descendants()->leaves(1)->defaultOrder()->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(0, $nodes);
    }

    public function testDescendants(): void
    {
        static::makeTree(null, 1, 3, 3, 1);
        $node = static::$modelClass::where(['title' => 'child 1.3'])->first();

        $nodes = $node->descendants()->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(6, $nodes);
        static::assertEquals([
            'child 1.3.3',
            'child 1.3.3.1',
            'child 1.3.2',
            'child 1.3.2.1',
            'child 1.3.1',
            'child 1.3.1.1',
        ], $nodes->toArray());


        $nodes = $node->descendants(1)->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(3, $nodes);
        static::assertEquals([
            'child 1.3.3',
            'child 1.3.2',
            'child 1.3.1',
        ], $nodes->toArray());


        $nodes = $node->descendants(0)->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(0, $nodes);


        $nodes = $node->descendants(1, true)->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(4, $nodes);
        static::assertEquals([
            'child 1.3',
            'child 1.3.3',
            'child 1.3.2',
            'child 1.3.1',
        ], $nodes->toArray());


        $nodes = $node->descendants(1, true, true)->get()->map(static function ($item) {
            return $item->title;
        });

        static::assertCount(4, $nodes);
        static::assertEquals([
            'child 1.3',
            'child 1.3.1',
            'child 1.3.2',
            'child 1.3.3',
        ], $nodes->toArray());

    }


    public function testWhereDescendantOf(): void
    {
        static::makeTree(null, 1, 3, 3, 1);
        $node = static::$modelClass::where(['title' => 'child 1.3'])->first();

        static::assertEquals('child 1.3', $node->title);

        $list = static::$modelClass::whereDescendantOf($node->getKey())->get();
        static::assertCount(6, $list);


        $root = $node->getRoot();

        static::assertTrue($root->isRoot());

        $list = static::$modelClass::whereDescendantOf($root)->get();
        static::assertCount(21, $list);
    }

    /*
        public function testWhereAncestorOf(): void
        {
            static::createTree();

            $node51 = static::$modelClass::where(['title' => 'child 5.1'])->first();
            static::assertEquals('child 5.1', $node51->title);

            $list = static::$modelClass::whereAncestorOf($node51->getKey())->get();

            static::assertCount(4, $list);


            $root = $node51->getRoot();

            static::assertTrue($root->isRoot());

            $list = static::$modelClass::whereAncestorOf($root)->get();
            static::assertCount(0, $list);
        }*/

}
