<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class RelationAncestorsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * @return array{0: Category, 1: Category, 2: Category}
     */
    private function buildChain(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $node2 */
        $node2 = static::model(['title' => 'level 2']);
        $node2->appendTo($root)->save();

        /** @var Category $node3 */
        $node3 = static::model(['title' => 'level 3']);
        $node3->appendTo($node2)->save();

        $root->refresh();
        $node2->refresh();
        $node3->refresh();

        return [
            $root,
            $node2,
            $node3,
        ];
    }

    #[Test]
    public function ancestorsQuery(): void
    {
        [
            $root,
            $node2,
            $node3,
        ] = $this->buildChain();

        static::assertEquals(0, $root->ancestors()->count());
        static::assertEquals(1, $node2->ancestors()->count());
        static::assertEquals(2, $node3->ancestors()->count());

        $ancestors = $node3->ancestors()->get();
        static::assertCount(2, $ancestors);
        static::assertTrue($root->isEqualTo($ancestors[0]));
        static::assertTrue($node2->isEqualTo($ancestors[1]));
    }
}
