<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class CollectionTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function linkNodes(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($modelRoot)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $preQueryCount      = count(static::model()->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 1;

        /** @var Collection $collection */
        $collection = static::model()::all();

        static::assertCount(4, $collection);

        $collection->linkNodes();

        static::assertCount(4, $collection);

        $roots = $collection->filter(fn(Category $model) => $model->isRoot());
        static::assertCount(1, $roots);

        /** @var Category $root */
        $root = $roots->first();
        static::assertNull($root->parent);
        static::assertCount(1, $root->children);

        /** @var Category $expNode1 */
        $expNode1 = $root->children->first();
        static::assertCount(1, $expNode1->children);
        static::assertTrue($root->isEqualTo($expNode1->parent));

        /** @var Category $expNode21 */
        $expNode21 = $expNode1->children->first();
        static::assertCount(1, $expNode21->children);
        static::assertTrue($expNode1->isEqualTo($expNode21->parent));

        /** @var Category $expNode31 */
        $expNode31 = $expNode21->children->first();
        static::assertCount(0, $expNode31->children);
        static::assertTrue($expNode21->isEqualTo($expNode31->parent));

        static::assertCount($expectedQueryCount, $root->getConnection()->getQueryLog());
    }

    #[Test]
    public function toTree(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($modelRoot)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $preQueryCount      = count(static::model()->getConnection()->getQueryLog());
        $expectedQueryCount = $preQueryCount + 1;

        /** @var Collection $collection */
        $collection = static::model()::all();

        static::assertCount(4, $collection);

        $treeCollection = $collection->toTree(setParentRelations: true);

        static::assertCount(1, $treeCollection);

        $roots = $treeCollection->getRoots();
        static::assertCount(1, $roots);

        /** @var Category $root */
        $root = $roots->first();
        static::assertNull($root->parent);
        static::assertCount(1, $root->children);

        /** @var Category $expNode1 */
        $expNode1 = $root->children->first();
        static::assertCount(1, $expNode1->children);
        static::assertTrue($root->isEqualTo($expNode1->parent));

        /** @var Category $expNode21 */
        $expNode21 = $expNode1->children->first();
        static::assertCount(1, $expNode21->children);
        static::assertTrue($expNode1->isEqualTo($expNode21->parent));

        /** @var Category $expNode31 */
        $expNode31 = $expNode21->children->first();
        static::assertCount(0, $expNode31->children);
        static::assertTrue($expNode21->isEqualTo($expNode31->parent));

        static::assertCount($expectedQueryCount, $root->getConnection()->getQueryLog());

        static::assertEquals(4, $treeCollection->totalCount());
    }

    #[Test]
    public function fillMissingIntermediateNodes(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($modelRoot)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        /** @var Collection $collection */
        $collection = new Collection([$node31]);

        $collection->fillMissingIntermediateNodes();

        static::assertCount(4, $collection);
    }

    #[Test]
    public function toBreadcrumbs(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($modelRoot)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        /** @var Collection $collection */
        $collection = new Collection([$node31]);

        $treeCollection = $collection->toBreadcrumbs();

        static::assertCount(1, $treeCollection);

        $roots = $treeCollection->getRoots();
        static::assertCount(1, $roots);

        /** @var Category $root */
        $root = $roots->first();
        static::assertNull($root->parent);
        static::assertCount(1, $root->children);

        /** @var Category $expNode1 */
        $expNode1 = $root->children->first();
        static::assertCount(1, $expNode1->children);
        static::assertTrue($root->isEqualTo($expNode1->parent));

        /** @var Category $expNode21 */
        $expNode21 = $expNode1->children->first();
        static::assertCount(1, $expNode21->children);
        static::assertTrue($expNode1->isEqualTo($expNode21->parent));

        /** @var Category $expNode31 */
        $expNode31 = $expNode21->children->first();
        static::assertCount(0, $expNode31->children);
        static::assertTrue($expNode21->isEqualTo($expNode31->parent));

        static::assertEquals(4, $treeCollection->totalCount());
    }
}
