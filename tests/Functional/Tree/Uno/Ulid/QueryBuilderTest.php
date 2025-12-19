<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno\Ulid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\CategoryWithUlid;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CategoryWithUlid>
     */
    protected static function modelClass(): string
    {
        return CategoryWithUlid::class;
    }

    #[Test]
    public function root(): void
    {
        /** @var CategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var CategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var CategoryWithUlid $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $modelRoot->refresh();

        static::assertTrue($modelRoot->isEqualTo($node31->root()->first()));
        static::assertCount(1, $node31->root()->get());
        static::assertTrue($modelRoot->isEqualTo($node31->root()->get()->first()));
        static::assertTrue($modelRoot->isEqualTo($node31->getRoot()));

        static::assertTrue($modelRoot->isEqualTo(CategoryWithUlid::root()->first()));
        static::assertCount(1, CategoryWithUlid::root()->get());
        static::assertTrue($modelRoot->isEqualTo(CategoryWithUlid::root()->get()->first()));

        static::assertEquals(26, strlen($modelRoot->id));
        static::assertEquals(26, strlen($node21->id));
        static::assertEquals(26, strlen($node31->id));
    }

    #[Test]
    public function notRoot(): void
    {
        /** @var CategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var CategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var CategoryWithUlid $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $list = CategoryWithUlid::notRoot()->get();

        static::assertCount(2, $list);
    }
}
