<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno\Ulid;

use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\CategoryWithUlid;
use PHPUnit\Framework\Attributes\Test;

class DeleteTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CategoryWithUlid>
     */
    protected static function modelClass(): string
    {
        return CategoryWithUlid::class;
    }

    #[Test]
    public function deleteRoot(): void
    {
        /** @var CategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $this->expectException(DeleteRootException::class);

        $modelRoot->delete();
    }

    #[Test]
    public function deleteNode(): void
    {
        /** @var CategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var CategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->prependTo($modelRoot)->save();

        $modelRoot->refresh();
        static::assertTrue($node21->isLeaf());
        static::assertTrue($node21->isChildOf($modelRoot));
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());


        static::assertTrue($node21->delete());

        $modelRoot->refresh();
        static::assertTrue($modelRoot->isLeaf());
        static::assertEmpty($modelRoot->children()->count());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(2, $modelRoot->rightValue());
    }

    #[Test]
    public function deleteNodeWithLineChildren(): void
    {
        /** @var CategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var CategoryWithUlid $nodeToDelete */
        $nodeToDelete = static::model(['title' => 'deletable node']);
        $nodeToDelete->appendTo($modelRoot)->save();

        /** @var CategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($nodeToDelete)->save();

        /** @var CategoryWithUlid $node22 */
        $node22 = static::model(['title' => 'child 2.2']);
        $node22->appendTo($nodeToDelete)->save();

        $modelRoot->refresh();
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(8, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());
        static::assertSame(3, $modelRoot->descendants()->count());

        $nodeToDelete->deleteWithChildren();

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isLeaf());
        static::assertEmpty($modelRoot->children()->count());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(2, $modelRoot->rightValue());
    }
}
