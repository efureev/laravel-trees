<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Config\Operation;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class NodeTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function getBounds(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $this->assertIsArray($modelRoot->getBounds());
        $this->assertCount(4, $modelRoot->getBounds());
        $this->assertEquals(1, $modelRoot->getBounds()[0]);
        $this->assertEquals(2, $modelRoot->getBounds()[1]);
        $this->assertEquals(0, $modelRoot->getBounds()[2]);
        $this->assertEquals(null, $modelRoot->getBounds()[3]);
    }


    #[Test]
    public function getNodeBoundsByModel(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $data = $modelRoot->getNodeBounds($modelRoot);

        static::assertIsArray($data);
        static::assertCount(4, $data);
    }

    #[Test]
    public function getNodeBoundsById(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();


        $data = $modelRoot->getNodeBounds($modelRoot->getKey());

        static::assertIsArray($data);
        static::assertCount(4, $data);
    }

    #[Test]
    public function testGetNodeData(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $data = $modelRoot->getNodeData($modelRoot->id);
        static::assertEquals(['lft' => 1, 'rgt' => 2, 'lvl' => 0, 'parent_id' => null], $data);
    }
    /**
     * `trace()` is the debug dump: bounds and level, plus the deferred-operation bookkeeping
     * that is otherwise invisible from outside.
     */
    #[Test]
    public function traceReportsBoundsAndPendingOperation(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        $root = $root->refresh();

        static::assertSame(
            [
                'left'  => 1,
                'right' => 2,
                'level' => 0,
                'tech'  => [
                    'forceSave' => false,
                    'operation' => null,
                    'node'      => null,
                ],
            ],
            $root->trace()
        );

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        // Reported off the model's own columns, so the node has to be saved first — a pending
        // operation on an unsaved node has no bounds to dump.
        $child = $child->refresh();
        $child->appendTo($root->refresh());

        $trace = $child->trace();

        static::assertSame(Operation::AppendTo, $trace['tech']['operation']);
        static::assertSame($root->getKey(), $trace['tech']['node']);
        static::assertSame(2, $trace['left']);
    }
}
