<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\ArchivedCategory;
use PHPUnit\Framework\Attributes\Test;

class SoftDeleteTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<ArchivedCategory>
     */
    protected static function modelClass(): string
    {
        return ArchivedCategory::class;
    }

    #[Test]
    public function deleteRoot(): void
    {
        /** @var ArchivedCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        $this->expectException(DeleteRootException::class);

        $modelRoot->delete();
    }

    #[Test]
    public function deleteNode(): void
    {
        /** @var ArchivedCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var ArchivedCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        $modelRoot->refresh();
        static::assertTrue($node21->isLeaf());
        static::assertTrue($node21->isChildOf($modelRoot));
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());

        // soft delete node
        static::assertTrue($node21->delete());

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isLeaf());
        static::assertEmpty($modelRoot->children()->count());
        static::assertEmpty($modelRoot->descendants()->count());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());

        // children with Trashed nodes
        static::assertEquals(1, $modelRoot->children()->withTrashed()->count());
        static::assertEquals(1, $modelRoot->childrenWithTrashed()->count());
        static::assertEquals(1, $modelRoot->childrenWithTrashed->count());
    }

    #[Test]
    public function deleteNodeWithChildren(): void
    {
        /** @var ArchivedCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var ArchivedCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var ArchivedCategory $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $modelRoot->refresh();
        $node21->refresh();
        static::assertFalse($node21->isLeaf());
        static::assertTrue($node21->isChildOf($modelRoot));
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());
        static::assertSame(2, $modelRoot->descendants()->count());

        static::assertNull($node21->{$node21->getDeletedAtColumn()});

        // soft delete node
        static::assertTrue($node21->delete());

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isLeaf());
        static::assertEmpty($modelRoot->children()->count());
        static::assertEquals(1, $modelRoot->descendants()->count());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());

        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());
        static::assertEquals(1, $node21->levelValue());
        static::assertNotNull($node21->{$node21->getDeletedAtColumn()});

        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());
        static::assertEquals(2, $node31->levelValue());
        static::assertNull($node31->{$node31->getDeletedAtColumn()});

        // children with Trashed nodes
        static::assertEquals(1, $modelRoot->children()->withTrashed()->count());
        static::assertEquals(1, $modelRoot->childrenWithTrashed()->count());
        static::assertEquals(1, $modelRoot->childrenWithTrashed->count());
        static::assertEquals(2, $modelRoot->descendants()->withTrashed()->count());


        // Recover Node

        $node21->restore();

        $modelRoot->refresh();
        $node21->refresh();

        static::assertFalse($node21->isLeaf());
        static::assertTrue($node21->isChildOf($modelRoot));
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());
        static::assertSame(1, $modelRoot->children()->count());
        static::assertSame(2, $modelRoot->descendants()->count());

        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());
        static::assertEquals(1, $node21->levelValue());

        static::assertEquals(3, $node31->leftValue());
        static::assertEquals(4, $node31->rightValue());
        static::assertEquals(2, $node31->levelValue());
    }

    /**
     * The existence subquery aliases the table, and SoftDeletingScope qualifies its column
     * at execution time — the two must agree or the query references a missing table.
     */
    #[Test]
    public function hasDescendantsWorksWithSoftDeletes(): void
    {
        /** @var ArchivedCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var ArchivedCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        $branches = ArchivedCategory::query()->has('descendants')->get();

        static::assertCount(1, $branches);
        static::assertSame($modelRoot->getKey(), $branches->first()->getKey());

        $leaves = ArchivedCategory::query()->doesntHave('descendants')->get();

        static::assertCount(1, $leaves);
        static::assertSame($node21->getKey(), $leaves->first()->getKey());
    }

    #[Test]
    public function descendantsExcludeTrashedNodes(): void
    {
        /** @var ArchivedCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var ArchivedCategory $keep */
        $keep = static::model(['title' => 'keep']);
        $keep->appendTo($modelRoot)->save();

        /** @var ArchivedCategory $trash */
        $trash = static::model(['title' => 'trash']);
        $trash->appendTo($modelRoot)->save();

        static::assertEquals(2, $modelRoot->refresh()->descendants()->count());

        $trash->delete();

        static::assertEquals(1, $modelRoot->refresh()->descendants()->count());
        static::assertSame(
            [$keep->getKey()],
            $modelRoot->descendants()->get()->modelKeys()
        );

        // The node is only hidden by the global scope, not gone.
        static::assertEquals(2, $modelRoot->descendants()->withTrashed()->count());
    }

    #[Test]
    public function ancestorsExcludeTrashedNodes(): void
    {
        /** @var ArchivedCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var ArchivedCategory $middle */
        $middle = static::model(['title' => 'middle']);
        $middle->appendTo($modelRoot)->save();

        /** @var ArchivedCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($middle)->save();

        static::assertEquals(2, $leaf->refresh()->ancestors()->count());

        $middle->refresh()->delete();

        static::assertEquals(1, $leaf->refresh()->ancestors()->count());
        static::assertEquals(2, $leaf->ancestors()->withTrashed()->count());
    }
}
