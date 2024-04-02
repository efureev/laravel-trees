<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\UseRootHelper;
use Fureev\Trees\Tests\models\v5\Category;

class NodeTreeTest extends AbstractFunctionalTreeTestCase
{
    use UseRootHelper;

    protected static function modelClass(): string
    {
        return Category::class;
    }


    /*


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

        $this->assertTrue($root->isEqualTo($node31->parent));
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



    public function testUsesSoftDelete(): void
    {
        $model = new static::$modelClass(['id' => 1, 'title' => 'root node']);
        $this->assertFalse($model::isSoftDelete());
    }


    */
}
