<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\CustomModel;

class CollectionCustomModelTest extends AbstractUnitTestCase
{

    /** @var CustomModel|string */
    protected static $modelClass = CustomModel::class;

    public function testLinkNodes(): void
    {
        $childrenTree = [2, 3, 2, 3];
        static::makeTree(null, ...$childrenTree);

        static::assertEquals(56, static::sum($childrenTree));
        static::assertCount(static::sum($childrenTree), static::$modelClass::all());

        $preQueryCount = count((new static::$modelClass)->getConnection()->getQueryLog());

        /** @var CustomModel $root */
        $roots = static::$modelClass::root()->get();


        static::assertCount(2, $roots);
        $root1 = $roots->first();
        $root2 = $roots->last();

        $collection1 = static::$modelClass::byTree($root1->treeValue())->get();
        $collection2 = static::$modelClass::byTree($root2->treeValue())->get();

        static::assertCount(28, $collection1);
        static::assertCount(28, $collection2);


        static::assertCount($preQueryCount += 3, $root1->getConnection()->getQueryLog());

        foreach ($roots as $root) {
            static::assertTrue($root->treeAttribute()->name() === static::$modelClass::TREE_ID);
            static::assertTrue($root->parentAttribute()->name() === static::$modelClass::PARENT_ID);
            static::assertTrue($root->leftAttribute()->name() === 'left_offset');
            static::assertTrue($root->rightAttribute()->name() === 'right_offset');
            static::assertTrue($root->levelAttribute()->name() === 'deeeeep');

            $collection = static::$modelClass::byTree($root->treeValue())->get();
            $preQueryCount++;
            static::assertCount($preQueryCount, $root->getConnection()->getQueryLog());
            $collection->linkNodes();

            $collectionRoot  = $collection->where($root->parentAttribute()->name(), '=', null)->first();
            $collectionRoot2 = $collection->getRoots()->first();
            static::assertEquals($collectionRoot, $collectionRoot2);

            static::assertCount($preQueryCount, $root->getConnection()->getQueryLog());

            static::assertCount(3, $collectionRoot->children);
            static::assertNull($collectionRoot->parent);

            static::assertCount($preQueryCount, $root->getConnection()->getQueryLog());

            foreach ($collectionRoot->children as $children1) {
                static::assertCount(2, $children1->children);
                static::assertTrue($collectionRoot->equalTo($children1->parent));

                foreach ($children1->children as $children2) {
                    static::assertCount(3, $children2->children);
                    static::assertTrue($children1->equalTo($children2->parent));
                }
            }
        }

        static::assertCount($preQueryCount, $root->getConnection()->getQueryLog());
    }

    public function testWoLinkNodes(): void
    {
        $childrenTree = [2, 3, 2, 3];
        static::makeTree(null, ...$childrenTree);

        $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 2;

        $root       = static::$modelClass::root()->first();
        $collection = static::$modelClass::byTree($root->treeValue())->get();

        /** @var CustomModel $root */
        $root = $collection->getRoots()->first();

        static::assertCount($expectedQueryCount, $root->getConnection()->getQueryLog());

        static::assertCount(3, $root->children);
        static::assertNull($root->parent);
        static::assertCount($expectedQueryCount + 1, $root->getConnection()->getQueryLog());

        foreach ($root->children as $children1) {
            static::assertCount(2, $children1->children);
            static::assertTrue($root->equalTo($children1->parent));
        }

        static::assertCount($expectedQueryCount + 7, $root->getConnection()->getQueryLog());
    }

    public function testToTreeWithRootNode(): void
    {
        $childrenNodesMap = [2, 3, 2, 3];
        static::makeTree(null, ...$childrenNodesMap);

        $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 2;

        $root = static::$modelClass::root()->first();
        $list = static::$modelClass::byTree($root->treeValue())->get();

        static::assertCount(static::sum($childrenNodesMap) / 2, $list);

        /** @var CustomModel $root */
        $root = $list->getRoots()->first();

        $tree = $list->toTree($root);

        static::assertCount(3, $tree);
        static::assertNull($root->parent);

        foreach ($root->children as $children1) {
            static::assertCount(2, $children1->children);
            static::assertTrue($root->equalTo($children1->parent));
        }

        static::assertCount($expectedQueryCount + $root->children->count(), $root->getConnection()->getQueryLog());
    }

    public function testToTreeWithOutRootNode(): void
    {
        $childrenNodesMap = [2, 3];
        static::makeTree(null, ...$childrenNodesMap);

        $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 1;

        $list = static::$modelClass::all();

        static::assertCount(static::sum($childrenNodesMap), $list);

        $tree = $list->toTree();

        static::assertCount(2, $tree);


        foreach ($tree as $page) {
            static::assertCount(3, $page['children']);
        }

        static::assertCount($expectedQueryCount, $list->first()->getConnection()->getQueryLog());
    }

    public function testToTreeCustomLevels(): void
    {
        $childrenNodesMap = [2, 3, 1, 2];
        static::makeTree(null, ...$childrenNodesMap);

        foreach ($childrenNodesMap as $level => $childrenCount) {
            $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
            $expectedQueryCount = $preQueryCount + 1;


            $list = static::$modelClass::toLevel($level)->get();
            static::assertCount(static::sum($childrenNodesMap, $level), $list);

            static::assertEmpty(
                $list->filter(function ($item) use ($level) {
                    return $item->levelValue() > $level;
                })
            );

            static::assertCount(
                static::sum($childrenNodesMap, $level),
                $list->filter(function ($item) use ($level) {
                    return $item->levelValue() <= $level;
                })
            );

            /** @var Collection $tree */
            $tree = $list->toTree();

            static::assertCount(2, $tree);

            static::assertCount($expectedQueryCount, $list->first()->getConnection()->getQueryLog());
        }
    }

    public function testToTreeArrayMultiRoots(): void
    {
        $childrenNodesMap = [5, 3, 2];
        static::makeTree(null, ...$childrenNodesMap);

        $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 1;

        $list = static::$modelClass::all();

        static::assertCount(static::sum($childrenNodesMap), $list);

        $tree = $list->toTree()->toArray();

        static::assertCount(5, $tree);


        foreach ($tree as $pages) {
            static::assertCount(3, $pages['children']);

            foreach ($pages['children'] as $page) {
                static::assertCount(2, $page['children']);
            }
        }

        static::assertCount($expectedQueryCount, $list->first()->getConnection()->getQueryLog());
    }

    public function testGetRoots(): void
    {
        static::makeTree(null, 6, 1, 2, 1);

        $list               = static::$modelClass::all();
        $expectedQueryCount = count((new static::$modelClass)->getConnection()->getQueryLog());

        static::assertCount(36, $list);

        $roots = $list->getRoots();

        static::assertCount(6, $roots);

        static::assertCount($expectedQueryCount, $list->first()->getConnection()->getQueryLog());
    }
}
