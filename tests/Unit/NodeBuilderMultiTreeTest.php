<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\models\Page;

class NodeBuilderMultiTreeTest extends AbstractUnitTestCase
{
    /** @var Page|string */
    protected static $modelClass = Page::class;


    public function testByTree(): void
    {
        static::makeTree(null, 3, 2, 1);

        $roots = self::$modelClass::root()->get();

        /** @var Page $root */
        foreach ($roots as $node) {
            $nodesRootCheck = self::$modelClass::root()->byTree($node->treeValue())->get();
            static::assertCount(1, $nodesRootCheck);
            $nodeRootCheck = $nodesRootCheck->first();
            static::assertInstanceOf(self::$modelClass, $nodeRootCheck);
            static::assertTrue($node->equalTo($nodeRootCheck));


            $nodesCheck = self::$modelClass::byTree($node->treeValue())->get();
            static::assertCount(5, $nodesCheck);
            $treeId = $node->treeValue();

            static::assertCount(
                5,
                $nodesCheck->map->tree_id->filter(
                    static function ($item) use ($treeId) {
                        return $item === $treeId;
                    }
                )
            );
        }
    }
}
