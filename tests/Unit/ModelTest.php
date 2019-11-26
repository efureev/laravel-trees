<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\Page;

class ModelTest extends AbstractUnitTestCase
{
    /** @var string */
    protected static $modelClass = Page::class;

    public function testModel(): void
    {
        /** @var Page $model */
        $model = new static::$modelClass([
            '_setRoot' => true,
            'title' => 'Root node',
        ]);

        static::assertEquals($model->toArray(), ['title' => 'Root node']);
        $model->save();
        $arr = $model->toArray();
        static::assertArrayHasKey($model->getKeyName(), $arr);
        static::assertNotNull($model->getKey());

        /** @var Page $child */
        $child = new static::$modelClass([
            'title' => 'node',
        ]);
        $child->appendTo($model)->save();

        $arr = $child->toArray();
        static::assertArrayHasKey($child->getKeyName(), $arr);
        static::assertArrayHasKey('title', $arr);
        static::assertNotNull($child->getKey());

        /** @var Collection $list */
        $list = static::$modelClass::all();

        $tree = $list->toTree();
        $treeArray = $tree->toArray();
        static::assertIsArray($treeArray);

        foreach ($treeArray as $treeNode) {
            static::assertArrayHasKey('children', $treeNode);
            static::assertCount(1, $treeNode['children']);
        }
    }

}
