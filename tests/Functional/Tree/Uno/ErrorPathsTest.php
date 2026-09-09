<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\Table;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

/**
 * The refusals. Every one of these is a guard the package opens a method with, and none of them
 * had ever run — a guard nothing exercises is a guard nobody knows still works.
 */
class ErrorPathsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    private function makeRoot(): Category
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        return $root->refresh();
    }

    /**
     * Not the "can not insert" case already covered: this one moves a node that already exists.
     */
    #[Test]
    public function movingAnExistingNodeBesideTheRootIsRefused(): void
    {
        $root = $this->makeRoot();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root)->save();

        $this->expectException(UniqueRootException::class);
        $this->expectExceptionMessage('Can not move a node before/after root. Model must be "MultiTree"');

        $child->refresh()->insertAfter($root->refresh())->save();
    }

    #[Test]
    public function settingATreeOnASingleTreeModelIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model does not implement MultiTree');

        static::model(['title' => 'x'])->setTree(1);
    }

    #[Test]
    public function requiringAMissingNodeRaises(): void
    {
        $this->makeRoot();

        $this->expectException(ModelNotFoundException::class);

        static::model()->newNestedSetQuery()->getNodeData(999999, true);
    }

    #[Test]
    public function tableRefusesAModelThatIsNotANode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model must be a node.');

        Table::fromModel(new NonTreeModel());
    }

    /**
     * `saveAsRoot()` on a node that is already a root saves it as it stands rather than going
     * through the promotion again.
     */
    #[Test]
    public function saveAsRootOnAnExistingRootJustSaves(): void
    {
        $root = $this->makeRoot();

        $root->title = 'renamed';

        static::assertTrue($root->saveAsRoot());
        static::assertSame('renamed', $root->refresh()->title);
        static::assertSame([1, 2], [$root->leftValue(), $root->rightValue()]);
    }

    /**
     * `descendantsQuery()` can walk the subtree from the far edge instead of the near one.
     */
    #[Test]
    public function descendantsCanBeOrderedFromTheOtherBound(): void
    {
        $root = $this->makeRoot();

        foreach (['a', 'b'] as $title) {
            /** @var Category $node */
            $node = static::model(['title' => $title]);
            $node->appendTo($root->refresh())->save();
        }

        $titles = $root->refresh()
            ->newNestedSetQuery()
            ->descendantsQuery(backOrder: true)
            ->get()
            ->pluck('title')
            ->all();

        static::assertEqualsCanonicalizing(
            [
                'a',
                'b',
            ],
            $titles
        );
    }
}
