<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\Ulid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithUlid;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategoryWithUlid>
     */
    protected static function modelClass(): string
    {
        return MultiCategoryWithUlid::class;
    }

    #[Test]
    public function parentsByModelId(): void
    {
        /** @var MultiCategoryWithUlid $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        /** @var MultiCategoryWithUlid $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        /** @var MultiCategoryWithUlid $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $collection = MultiCategoryWithUlid::parentsByModelId($node31->id)->get();
        static::assertCount(2, $collection);
        static::assertEquals('root node', $collection[0]->title);
        static::assertEquals('child 2.1', $collection[1]->title);

        $collectionWithSelf = MultiCategoryWithUlid::parentsByModelId($node31->id, andSelf: true)->get();
        static::assertCount(3, $collectionWithSelf);
        static::assertEquals('child 3.1', $collectionWithSelf[2]->title);
    }

    #[Test]
    public function roots(): void
    {
        static::model(['title' => 'root 1'])->save();
        static::model(['title' => 'root 2'])->save();

        $roots = MultiCategoryWithUlid::root()->get();
        static::assertCount(2, $roots);
    }

    #[Test]
    public function byTree(): void
    {
        /** @var MultiCategoryWithUlid $root1 */
        $root1 = static::model(['title' => 'root 1']);
        $root1->save();

        static::model(['title' => 'root 2'])->save();

        static::model(['title' => 'child 1.1'])->appendTo($root1)->save();

        $tree1Nodes = MultiCategoryWithUlid::byTree($root1->tree_id)->get();
        static::assertCount(2, $tree1Nodes);
    }

    #[Test]
    public function ancestorsAndDescendants(): void
    {
        /** @var MultiCategoryWithUlid $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        /** @var MultiCategoryWithUlid $node2 */
        $node2 = static::model(['title' => 'level 2']);
        $node2->appendTo($root)->save();

        /** @var MultiCategoryWithUlid $node3 */
        $node3 = static::model(['title' => 'level 3']);
        $node3->appendTo($node2)->save();

        $root->refresh();
        $node2->refresh();
        $node3->refresh();

        // Descendants
        static::assertCount(2, $root->descendants()->get());
        static::assertCount(1, $node2->descendants()->get());
        static::assertCount(0, $node3->descendants()->get());

        // Ancestors
        static::assertCount(2, $node3->ancestors()->get());
        static::assertCount(1, $node2->ancestors()->get());
        static::assertCount(0, $root->ancestors()->get());

        static::assertEquals('root', $node3->ancestors()->get()[0]->title);
        static::assertEquals('level 2', $node3->ancestors()->get()[1]->title);
    }

    #[Test]
    public function siblings(): void
    {
        /** @var MultiCategoryWithUlid $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        /** @var MultiCategoryWithUlid $child1 */
        $child1 = static::model(['title' => 'child 1']);
        $child1->appendTo($root)->save();

        /** @var MultiCategoryWithUlid $child2 */
        $child2 = static::model(['title' => 'child 2']);
        $child2->appendTo($root)->save();

        /** @var MultiCategoryWithUlid $child3 */
        $child3 = static::model(['title' => 'child 3']);
        $child3->appendTo($root)->save();

        $child1->refresh();
        $child2->refresh();
        $child3->refresh();

        static::assertTrue($child2->isEqualTo($child1->next()->first()));
        static::assertTrue($child1->isEqualTo($child2->prev()->first()));

        static::assertCount(2, $child1->nextNodes()->get());
        static::assertCount(1, $child2->nextNodes()->get());
        static::assertCount(0, $child3->nextNodes()->get());

        $prevNodes = $child1->prevNodes()->get();
        static::assertCount(1, $prevNodes);
        static::assertEquals('root', $prevNodes->first()->title);
        static::assertCount(2, $child2->prevNodes()->get());
        static::assertCount(3, $child3->prevNodes()->get());

        // Siblings
        static::assertCount(2, $child1->siblings()->get());
        static::assertCount(3, $child1->siblingsAndSelf()->get());
        static::assertTrue($child2->isEqualTo($child1->nextSibling()->first()));
        static::assertTrue($child1->isEqualTo($child2->prevSibling()->first()));
    }
}
