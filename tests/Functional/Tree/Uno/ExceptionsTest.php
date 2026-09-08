<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Exceptions\NotSupportedException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Behavioural coverage for the package exceptions on single-trees (category C).
 */
class ExceptionsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function appendingToUnsavedTargetNodeThrows(): void
    {
        $target = static::model(['title' => 'unsaved target']);

        /** @var Category $node */
        $node = static::model(['title' => 'node']);
        $node->prependTo($target);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not manipulate a node when the target node is a new record.');

        $node->save();
    }

    #[Test]
    public function savingNodeWithoutOperationIsNotSupported(): void
    {
        $model = static::model(['id' => 2, 'title' => 'node']);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('is not supported for inserting new nodes');

        $model->save();
    }

    /**
     * Promoting an existing node to a root is a multi-tree operation. It used to be a silent
     * no-op here, because makeRoot() left the model clean and Eloquent skipped the update
     * before beforeUpdate() could object.
     */
    #[Test]
    public function makeRootOnExistingNodeFailsForSingleTree(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can not move a node as the root when Model is not set to "MultiTree"');

        $child->refresh()->makeRoot()->save();
    }
}
