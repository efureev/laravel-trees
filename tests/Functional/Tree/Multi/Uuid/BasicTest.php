<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\Uuid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithUuid;
use PHPUnit\Framework\Attributes\Test;

class BasicTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategoryWithUuid>
     */
    protected static function modelClass(): string
    {
        return MultiCategoryWithUuid::class;
    }

    #[Test]
    public function createMultiTree(): void
    {
        /** @var MultiCategoryWithUuid $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategoryWithUuid $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        static::assertNotNull($root1->tree_id);
        static::assertNotNull($root2->tree_id);
        static::assertNotEquals($root1->tree_id, $root2->tree_id);

        static::assertEquals(36, strlen($root1->tree_id));
        static::assertEquals(36, strlen($root1->id));
        static::assertEquals(36, strlen($root2->tree_id));
        static::assertEquals(36, strlen($root2->id));

        /** @var MultiCategoryWithUuid $child11 */
        $child11 = static::model(['title' => 'child 1.1']);
        $child11->appendTo($root1)->save();

        static::assertEquals($root1->tree_id, $child11->tree_id);

        $root1->refresh();
        static::assertCount(1, $root1->children);
        static::assertCount(0, $root2->children);
    }

    #[Test]
    public function movingBetweenTrees(): void
    {
        /** @var MultiCategoryWithUuid $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        /** @var MultiCategoryWithUuid $root2 */
        $root2 = static::model(['title' => 'root 2']);
        $root2->save();

        /** @var MultiCategoryWithUuid $node */
        $node = static::model(['title' => 'node']);
        $node->appendTo($root1)->save();

        static::assertEquals($root1->tree_id, $node->tree_id);

        $node->appendTo($root2)->save();
        $node->refresh();
        $root2->refresh();

        static::assertEquals($root2->tree_id, $node->tree_id);
        static::assertTrue($node->isChildOf($root2));

        $root1->refresh();
        $root2->refresh();
        static::assertCount(0, $root1->children);
        static::assertCount(1, $root2->children);
    }
}
