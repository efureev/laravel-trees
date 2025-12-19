<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno\Ulid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\CategoryWithUlid;
use PHPUnit\Framework\Attributes\Test;

class ParentsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CategoryWithUlid>
     */
    protected static function modelClass(): string
    {
        return CategoryWithUlid::class;
    }

    #[Test]
    public function parents(): void
    {
        /** @var CategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var CategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        /** @var CategoryWithUlid $node22 */
        $node22 = static::model(['title' => 'child 2.2']);

        $node21->appendTo($modelRoot)->save();
        $node22->appendTo($modelRoot)->save();

        $node22->refresh();
        $modelRoot->refresh();

        static::assertEquals(26, strlen($node22->id));
        static::assertTrue($modelRoot->isEqualTo($node22->parent));

        $parents = $node22->parents();
        static::assertCount(1, $parents);
        static::assertEquals('root node', $parents->first()->title);
    }
}
