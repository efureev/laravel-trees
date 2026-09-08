<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class RootTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function createRootModel(): void
    {
        /** @var MultiCategory $model */
        $model = static::model(['title' => 'root node']);

        $model->makeRoot()->save();

        static::assertSame(1, $model->id);
        static::assertTrue($model->isRoot());

        static::assertNotNull($model->getRoot());
        static::assertInstanceOf(static::modelClass(), $model->getRoot());

        static::assertEquals($model->id, $model->getRoot()->id);
        static::assertEquals($model->title, $model->getRoot()->title);
        static::assertEquals(1, $model->leftValue());
        static::assertEquals(2, $model->rightValue());
        static::assertEquals($model->lvl, $model->getRoot()->lvl);
        static::assertSame(0, $model->getRoot()->lvl);

        static::assertEquals($model->tree_id, $model->getRoot()->tree_id);
        static::assertSame(1, $model->getRoot()->tree_id);

        static::assertEmpty($model->parents());
    }

    #[Test]
    public function createSeveralRoot(): void
    {
        /** @var MultiCategory $model */
        $model = static::model(['title' => 'root 1']);
        $model->makeRoot()->save();
        static::assertSame(1, $model->tree_id);

        $model2 = static::model(['title' => 'root 2']);
        $model2->makeRoot()->save();
        static::assertSame(2, $model2->tree_id);
    }

    #[Test]
    public function createSeveralRootWithoutMarkThemAsRoot(): void
    {
        /** @var MultiCategory $model */
        $model = static::model(['title' => 'root 1']);
        $model->save();
        static::assertSame(1, $model->tree_id);
        static::assertEquals(1, $model->leftValue());
        static::assertEquals(2, $model->rightValue());
        static::assertEmpty($model->parents());
        static::assertTrue($model->isLeaf());

        $model2 = static::model(['title' => 'root 2']);
        $model2->save();

        static::assertSame(2, $model2->tree_id);
        static::assertEquals(1, $model2->leftValue());
        static::assertEquals(2, $model2->rightValue());
        static::assertEmpty($model2->parents());

        static::assertNotEquals($model->tree_id, $model2->tree_id);
        static::assertTrue($model2->isLeaf());
    }

    #[Test]
    public function receiveRoots(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root 1']);
        $root->saveAsRoot();
        static::model(['title' => 'child 2.1'])->prependTo($root)->save();

        static::model(['title' => 'root 2'])->saveAsRoot();

        static::assertEquals(2, MultiCategory::root()->count());
    }

    /**
     * root -> branch -> leaf, all inside one tree.
     *
     * @return array<string, MultiCategory>
     */
    private function buildBranch(): array
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var MultiCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root)->save();

        /** @var MultiCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
        ];
    }

    #[Test]
    public function makeRootPromotesExistingNode(): void
    {
        $nodes = $this->buildBranch();

        $nodes['branch']->refresh()->makeRoot()->save();

        $promoted = $nodes['branch']->refresh();

        static::assertTrue($promoted->isRoot());
        static::assertNull($promoted->parentValue());
        static::assertSame(1, $promoted->leftValue());
        static::assertSame(0, $promoted->levelValue());
        static::assertNotSame($nodes['root']->refresh()->treeValue(), $promoted->treeValue());
    }

    #[Test]
    public function promotedNodeTakesItsSubtreeAlong(): void
    {
        $nodes = $this->buildBranch();

        $nodes['branch']->refresh()->makeRoot()->save();

        $promoted = $nodes['branch']->refresh();
        $leaf     = $nodes['leaf']->refresh();

        static::assertSame($promoted->treeValue(), $leaf->treeValue());
        static::assertSame(1, $leaf->levelValue());
        static::assertSame($promoted->getKey(), $leaf->parentValue());
        static::assertTrue($leaf->isChildOf($promoted));
    }

    #[Test]
    public function promotedNodeLeavesOldTreeConsistent(): void
    {
        $nodes = $this->buildBranch();

        static::assertSame(6, $nodes['root']->rightValue());

        $nodes['branch']->refresh()->makeRoot()->save();

        $oldRoot = $nodes['root']->refresh();

        // The gap left behind is closed: a lone root spans 1..2 again.
        static::assertSame(1, $oldRoot->leftValue());
        static::assertSame(2, $oldRoot->rightValue());
        static::assertFalse((new HealthyChecker(MultiCategory::class))->isBroken());
    }

    #[Test]
    public function saveAsRootPromotesExistingNode(): void
    {
        $nodes = $this->buildBranch();

        $nodes['branch']->refresh()->saveAsRoot();

        $promoted = $nodes['branch']->refresh();

        static::assertTrue($promoted->isRoot());
        static::assertNull($promoted->parentValue());
        static::assertSame(0, $promoted->levelValue());
    }

    #[Test]
    public function forceSaveStillPromotes(): void
    {
        $nodes = $this->buildBranch();

        $nodes['branch']->refresh()->makeRoot()->forceSave();

        $promoted = $nodes['branch']->refresh();

        static::assertTrue($promoted->isRoot());
        static::assertNull($promoted->parentValue());
        static::assertSame(1, $promoted->leftValue());
    }

    /**
     * Promoting a node into an explicitly chosen tree. The generator only ever produces
     * `max(tree_id) + 1`, so a zero identifier can only come from the caller — and a falsy
     * check would silently swap it for a generated one.
     */
    #[Test]
    public function promotesIntoAnExplicitlyChosenTree(): void
    {
        $nodes = $this->buildBranch();

        $nodes['branch']->refresh()->setTree(42)->makeRoot()->save();

        static::assertSame(42, $nodes['branch']->refresh()->treeValue());
        static::assertSame(42, $nodes['leaf']->refresh()->treeValue());
    }

    #[Test]
    public function promotesIntoTreeZero(): void
    {
        $nodes = $this->buildBranch();

        $nodes['branch']->refresh()->setTree(0)->makeRoot()->save();

        static::assertSame(0, $nodes['branch']->refresh()->treeValue());
        static::assertSame(0, $nodes['leaf']->refresh()->treeValue());
    }
}
