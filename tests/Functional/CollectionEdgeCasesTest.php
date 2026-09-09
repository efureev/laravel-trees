<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Collection;
use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use PHPUnit\Framework\Attributes\Test;

/**
 * The guards `Collection` opens each of its methods with: nothing to do, already done, or not a
 * tree at all. Each is a plain early return, and none of them ran.
 */
class CollectionEdgeCasesTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function anEmptyCollectionPassesThroughEveryMethod(): void
    {
        $collection = new Collection();

        static::assertSame($collection, $collection->toTree());
        static::assertSame($collection, $collection->linkNodes());

        $collection->fillMissingIntermediateNodes();

        static::assertTrue($collection->isEmpty());
    }

    #[Test]
    public function toTreeIsIdempotent(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root->refresh())->save();

        $tree = Category::query()->defaultOrder()->get()->toTree();

        // The second call finds the work already done and hands the same collection back.
        static::assertSame($tree, $tree->toTree());
        static::assertCount(1, $tree);
    }

    #[Test]
    public function linkNodesIsIdempotent(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $collection = Category::query()->defaultOrder()->get()->linkNodes();

        static::assertSame($collection, $collection->linkNodes());
    }

    #[Test]
    public function aCollectionOfSomethingElseIsRefused(): void
    {
        $collection = new Collection([new NonTreeModel()]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model should be a Tree Node');

        $collection->linkNodes();
    }
}
