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
}
