<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

class RelationDescendantsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function descendants(): void
    {
        /** @var Category $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();


        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        /** @var Category $node41 */
        $node41 = static::model(['title' => 'child 4.1']);
        /** @var Category $node32 */
        $node32 = static::model(['title' => 'child 3.2']);
        /** @var Category $node321 */
        $node321 = static::model(['title' => 'child 3.2.1']);

        $node21->appendTo($modelRoot)->save();
        $node31->appendTo($modelRoot)->save();
        $node41->appendTo($modelRoot)->save();
        $node32->appendTo($node31)->save();
        $node321->appendTo($node32)->save();

        $modelRoot->refresh();

        $list = $modelRoot->descendants();
        static::assertEquals(5, $list->count());
    }
}
