<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Exceptions\NotSupportedException;
use Fureev\Trees\Exceptions\TreeNeedValueException;
use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\Exceptions\UnsavedNodeException;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the package exceptions constructors and messages (category C).
 */
class ExceptionsTest extends AbstractUnitTestCase
{
    #[Test]
    public function allExceptionsExtendPackageException(): void
    {
        $model     = new Category(['title' => 'node']);
        $model->id = 7;

        static::assertInstanceOf(Exception::class, new DeleteRootException($model));
        static::assertInstanceOf(Exception::class, new DeletedNodeHasChildrenException($model));
        static::assertInstanceOf(Exception::class, new UniqueRootException($model));
        static::assertInstanceOf(Exception::class, new UnsavedNodeException($model));
        static::assertInstanceOf(Exception::class, new TreeNeedValueException());
        static::assertInstanceOf(Exception::class, new NotSupportedException());
    }

    #[Test]
    public function deleteRootExceptionMessageContainsKey(): void
    {
        $model     = new Category(['title' => 'root']);
        $model->id = 42;

        $exception = new DeleteRootException($model);

        static::assertSame('Root node does not support delete action. #42', $exception->getMessage());
    }

    #[Test]
    public function deletedNodeHasChildrenExceptionMessageContainsKey(): void
    {
        $model     = new Category(['title' => 'node']);
        $model->id = 13;

        $exception = new DeletedNodeHasChildrenException($model);

        static::assertSame('Deleted Node has children. #13', $exception->getMessage());
    }

    #[Test]
    public function uniqueRootExceptionUsesDefaultAndCustomMessage(): void
    {
        $model     = new Category(['title' => 'root']);
        $model->id = 5;

        static::assertSame(
            'Can not create more than one root. Exist: # 5',
            (new UniqueRootException($model))->getMessage()
        );

        static::assertSame(
            'custom root message',
            (new UniqueRootException($model, 'custom root message'))->getMessage()
        );
    }

    #[Test]
    public function unsavedNodeExceptionUsesDefaultAndCustomMessage(): void
    {
        $model = new Category(['title' => 'node']);

        static::assertSame('Node does not save', (new UnsavedNodeException($model))->getMessage());
        static::assertSame('boom', (new UnsavedNodeException($model, 'boom'))->getMessage());
    }

    #[Test]
    public function treeNeedValueExceptionUsesDefaultAndCustomMessage(): void
    {
        static::assertSame('Model must contained {tree_id} ID', (new TreeNeedValueException())->getMessage());
        static::assertSame('need tree', (new TreeNeedValueException('need tree'))->getMessage());
    }

    #[Test]
    public function notSupportedExceptionBuildsMessage(): void
    {
        static::assertSame('Not Supported', (new NotSupportedException())->getMessage());
        static::assertSame(
            'Not Supported: ' . Category::class,
            (new NotSupportedException(Category::class))->getMessage()
        );
        static::assertSame('Custom: Foo', (new NotSupportedException('Foo', 'Custom'))->getMessage());
    }
}
