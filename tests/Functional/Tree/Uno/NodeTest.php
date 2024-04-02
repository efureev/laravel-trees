<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
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
}
