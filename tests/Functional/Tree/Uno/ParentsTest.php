<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class ParentsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function parent(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 2.2']);
        /** @var Category $node23 */
        $node23 = static::model(['title' => 'child 2.3']);

        /** @var Category $node221 */
        $node221 = static::model(['title' => 'child 2.2.1']);
        /** @var Category $node2211 */
        $node2211 = static::model(['title' => 'child 2.2.1.1']);

        $node21->appendTo($modelRoot)->save();
        $node22->appendTo($modelRoot)->save();
        $node23->appendTo($modelRoot)->save();

        $node221->appendTo($node22)->save();
        $node2211->appendTo($node221)->save();

        $node221->refresh();
        $node22->refresh();
        $modelRoot->refresh();

        static::assertTrue($node221->isEqualTo($node2211->parent));
        static::assertTrue($node22->isEqualTo($node221->parent));
        static::assertTrue($modelRoot->isEqualTo($node22->parent));
    }

    #[Test]
    public function parents(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 2.2']);
        /** @var Category $node23 */
        $node23 = static::model(['title' => 'child 2.3']);

        /** @var Category $node221 */
        $node221 = static::model(['title' => 'child 2.2.1']);
        /** @var Category $node2211 */
        $node2211 = static::model(['title' => 'child 2.2.1.1']);

        $node21->appendTo($modelRoot)->save();
        $node22->appendTo($modelRoot)->save();
        $node23->appendTo($modelRoot)->save();

        $node221->appendTo($node22)->save();
        $node2211->appendTo($node221)->save();

        $node221->refresh();
        $node22->refresh();
        $modelRoot->refresh();

        // all of parents
        $parents = $node2211->parents();

        static::assertEquals(['root node', 'child 2.2', 'child 2.2.1'], $parents->map->title->toArray());
        static::assertCount(3, $parents);

        // parents from 1 level
        $parents2 = $node2211->parents(1);

        static::assertEquals(['child 2.2', 'child 2.2.1'], $parents2->map->title->toArray());
        static::assertCount(2, $parents2);

        // get 1 parent from 1 level

        /** @var Category $parent */
        $parent = $node2211->parentByLevel(1);

        static::assertTrue($node22->isEqualTo($parent));
    }
}
