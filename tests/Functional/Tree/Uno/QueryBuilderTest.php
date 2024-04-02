<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function root(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isEqualTo($node31->root()->first()));
        static::assertCount(1, $node31->root()->get());
        static::assertTrue($modelRoot->isEqualTo($node31->root()->get()->first()));
        static::assertTrue($modelRoot->isEqualTo($node31->getRoot()));

        static::assertTrue($modelRoot->isEqualTo(Category::root()->first()));
        static::assertCount(1, Category::root()->get());
        static::assertTrue($modelRoot->isEqualTo(Category::root()->get()->first()));
    }

    #[Test]
    public function notRoot(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $list = Category::notRoot()->get();

        static::assertCount(2, $list);
    }
}
