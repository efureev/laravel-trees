<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\PageUuid;

class CollectionTestV3 extends AbstractV3UnitTestCase
{

    /** @var PageUuid|string */
    protected static $modelClass = PageUuid::class;

    public function toTreeWithRootNode(): void
    {
        $childrenNodesMap = [2, 3, 2, 3];
        static::makeTree(null, ...$childrenNodesMap);

        $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 1;

        $list = static::$modelClass::byTree(1)->get();

        static::assertCount(static::sum($childrenNodesMap) / 2, $list);

        /** @var PageUuid $root */
        $root = $list->where('parent_id', '=', null)->first();

        $tree = $list->toTree($root);

        static::assertCount(3, $tree);
        static::assertNull($root->parent);

        foreach ($root->children as $children1) {
            static::assertCount(2, $children1->children);
            static::assertTrue($root->equalTo($children1->parent));
        }

        static::assertCount($expectedQueryCount + $root->children->count(), $root->getConnection()->getQueryLog());
    }


    public function toTreeCustomLevels(): void
    {
        $childrenNodesMap = [2, 3, 1, 2];
        static::makeTree(null, ...$childrenNodesMap);

        foreach ($childrenNodesMap as $level => $childrenCount) {
            $preQueryCount      = count((new static::$modelClass)->getConnection()->getQueryLog());
            $expectedQueryCount = $preQueryCount + 1;


            $list = static::$modelClass::toLevel($level)->get();
            static::assertCount(static::sum($childrenNodesMap, $level), $list);

            static::assertEmpty($list->filter(function ($item) use ($level) {
                return $item->levelValue() > $level;
            }));

            static::assertCount(static::sum($childrenNodesMap, $level), $list->filter(function ($item) use ($level) {
                return $item->levelValue() <= $level;
            }));

            /** @var Collection $tree */
            $tree = $list->toTree();

            static::assertCount(2, $tree);

            static::assertCount($expectedQueryCount, $list->first()->getConnection()->getQueryLog());
        }
    }

    public function toTreeArrayMultiRoots(): void
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


    public function toBreadcrumbs(): void
    {
        $childrenNodesMap = [1, 1, 3];
        static::makeTree(null, ...$childrenNodesMap);

        $list              = static::$modelClass::toLevel(3)->get();
        $listBeforeSpliced = $list->count();
        $chunkItem         = null;

        foreach ($list as $key => $item) {
            if ($item->parent !== null && $item->children !== null) {
                $chunkItem = $list->splice($key, 1)->first();
                break;
            }
        }

        static::assertEquals($listBeforeSpliced, $list->count() + 1);
        static::assertFalse($list->search(static fn($model) => $model->id === $chunkItem->id));

        /** @var Collection $tree */
        $tree = $list->toBreadcrumbs();

        $actual = $tree->first()->children->first();
        static::assertEquals($chunkItem->id, $actual->id);
        static::assertCount(3, $actual->children);
    }
}
