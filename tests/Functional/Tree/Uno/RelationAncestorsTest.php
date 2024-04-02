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

    #[Test]
    public function ancestors(): void
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


        $list = $node2211->ancestors;

        static::assertEquals(['root node', 'child 2.2', 'child 2.2.1'], $list->map->title->toArray());
        static::assertCount(3, $list);

        $list = $node2211->ancestors()->defaultOrder(SORT_DESC)->get();

        static::assertEquals(['child 2.2.1', 'child 2.2', 'root node'], $list->map->title->toArray());
        static::assertCount(3, $list);
    }
}
