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
            $nodesRootCheck = self::$modelClass::root()->byTree($node->getTree())->get();
            static::assertCount(1, $nodesRootCheck);
            $nodeRootCheck = $nodesRootCheck->first();
            static::assertInstanceOf(self::$modelClass, $nodeRootCheck);
            static::assertTrue($node->equalTo($nodeRootCheck));


            $nodesCheck = self::$modelClass::byTree($node->getTree())->get();
            static::assertCount(5, $nodesCheck);
            $nodeCheck = $nodesCheck->first();
            static::assertInstanceOf(self::$modelClass, $nodeCheck);
            static::assertEquals($nodeCheck->parent_id, $node->getKey());
        }
    }
}
