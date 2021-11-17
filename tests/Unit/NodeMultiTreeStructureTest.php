<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit;

use Faker\Provider\Uuid;
use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\Structure;

class NodeMultiTreeStructureTest extends AbstractUnitTestCase
{
    protected static $modelClass = Structure::class;

    public function testCreateAnyRootIntoOneTree(): void
    {
        $trees = [];
        for ($i = 1; $i <= 10; $i++) {
            $trees[] = $uuid = Uuid::uuid();

            $root = new static::$modelClass(
                [
                    'title'   => "Root structure site: #$i [$uuid]",
                    'tree_id' => $uuid,
                    'path'    => [0],
                ]
            );

            $root->save();

            foreach (['en', 'ru', 'es'] as $local) {
                $path   = $root->path;
                $path[] = $local;
                /** @var Structure $node */
                $node = new static::$modelClass(
                    [
                        'title'  => "[$local] Locale structure",
                        'path'   => $path,
                        'params' => ['local' => $local],
                    ]
                );

                $node->appendTo($root);
                $node->save();
            }
        }


        $listTotal = static::$modelClass::all();
        static::assertCount(static::sum([10, 3]), $listTotal);
        static::assertCount(10, $trees);


        foreach ($trees as $tree) {
            /** @var Collection $list */
            $list = static::$modelClass::byTree($tree)->get();
            static::assertCount(4, $list);

            $listNonRoot = static::$modelClass::notRoot()->byTree($tree)->get();
            static::assertCount(3, $listNonRoot);

            $locals = $listNonRoot->map(
                static function ($item) {
                    return $item->params['local'];
                }
            )->unique();

            static::assertCount(3, $locals);
            $childrenMap = [3, 2];

            /** @var Structure $node */
            foreach ($listNonRoot as $node) {
                $node->refresh();
                static::makeTree($node, ...$childrenMap);
                $node->refresh();
                $children = $node->descendants()->get();

                static::assertCount(static::sum($childrenMap), $children);
            }

            /** @var Collection $listNonRoot */
            $listNonRoot = static::$modelClass::byTree($tree)->get();
            static::assertCount(static::sum(array_merge([1, 3], $childrenMap)), $listNonRoot);
            $roots = $listNonRoot->getRoots();
            static::assertCount(1, $roots);

            /** @var Structure $root */
            $root = $roots->first();

            $data = $listNonRoot->toTree($root)->toArray();
            static::assertCount(3, $data);
            foreach ($data as $datum) {
                static::assertCount(3, $datum['children']);
            }
        }
    }
}
