<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Exceptions\NotSupportedException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\RefusingCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Three things an audit read as dead code, because nothing inside the package writes to them.
 * All three are reachable from outside it, which is what these pin down — the next reader
 * finds a test rather than an unexplained hook.
 */
class ExtensionPointsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * A single tree refuses a plain insert: there is nothing to attach the node to and the
     * package will not guess.
     */
    #[Test]
    public function aPlainSaveOnASingleTreeIsRefused(): void
    {
        $this->expectException(NotSupportedException::class);

        (new Category(['title' => 'plain']))->save();
    }

    /**
     * `_setRoot` is the attribute that lifts that refusal. Nothing in the package sets it —
     * the application does, and `markAsRoot()` removes it again before the insert, so it never
     * reaches a column.
     */
    #[Test]
    public function setRootAttributeMakesAPlainSaveCreateTheRoot(): void
    {
        $node = new Category(['title' => 'via _setRoot']);

        $node->_setRoot = true;
        $node->save();

        static::assertTrue($node->isRoot());
        static::assertSame(1, $node->leftValue());
        static::assertSame(2, $node->rightValue());
        static::assertSame(0, $node->levelValue());

        $stored = (array)static::model()->getConnection()->table('categories')->first();

        static::assertArrayNotHasKey('_setRoot', $stored);
    }

    /**
     * `onDeletingNodeHasChildren()` is empty in the package, so nothing there reads the answer.
     * An override does: throwing from it aborts the delete.
     */
    #[Test]
    public function theDeletingHookCanRefuseTheDelete(): void
    {
        /** @var RefusingCategory $root */
        $root = RefusingCategory::make(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var RefusingCategory $mid */
        $mid = RefusingCategory::make(['title' => 'mid']);
        $mid->appendTo($root->refresh())->save();

        /** @var RefusingCategory $leaf */
        $leaf = RefusingCategory::make(['title' => 'leaf']);
        $leaf->appendTo($mid->refresh())->save();

        try {
            $mid->refresh()->delete();
            static::fail('The hook should have refused the delete.');
        } catch (DeletedNodeHasChildrenException) {
            // expected
        }

        static::assertSame(3, RefusingCategory::query()->count());
    }
}
