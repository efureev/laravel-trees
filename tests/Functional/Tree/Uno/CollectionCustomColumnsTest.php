<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\CustomColumnsCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every tree column on this model is renamed, so a column name taken from the wrong place shows
 * up here and nowhere else — the rest of the collection suite runs on the default names, where
 * a mistake would look like a match.
 */
class CollectionCustomColumnsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CustomColumnsCategory>
     */
    protected static function modelClass(): string
    {
        return CustomColumnsCategory::class;
    }

    /**
     * root -> branch -> leaf, plus `aside` as a second child of the root.
     *
     * @return array<string, CustomColumnsCategory>
     */
    private function buildTree(): array
    {
        /** @var CustomColumnsCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var CustomColumnsCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var CustomColumnsCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        /** @var CustomColumnsCategory $aside */
        $aside = static::model(['title' => 'aside']);
        $aside->appendTo($root->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
            'aside'  => $aside->refresh(),
        ];
    }

    /**
     * `linkNodes()` groups the collection by the parent column. Here that column is `pid`.
     */
    #[Test]
    public function linkNodesGroupsByTheConfiguredParentColumn(): void
    {
        $this->buildTree();

        $nodes = CustomColumnsCategory::query()->defaultOrder()->get()->linkNodes();

        $byTitle = $nodes->keyBy('title');

        static::assertSame(
            [
                'branch',
                'aside',
            ],
            $byTitle->get('root')->children->pluck('title')->all()
        );
        static::assertSame(
            ['leaf'],
            $byTitle->get('branch')->children->pluck('title')->all()
        );
        static::assertCount(0, $byTitle->get('leaf')->children);

        static::assertNull($byTitle->get('root')->parent);
        static::assertSame('branch', $byTitle->get('leaf')->parent->title);
    }

    #[Test]
    public function toTreeBuildsTheHierarchy(): void
    {
        $this->buildTree();

        $tree = CustomColumnsCategory::query()->defaultOrder()->get()->toTree();

        static::assertCount(1, $tree);

        $root = $tree->first();

        static::assertSame('root', $root->title);
        static::assertCount(2, $root->children);
        static::assertSame('leaf', $root->children->first()->children->first()->title);
    }

    /**
     * `parentsByModelId()` joins a subquery and compares the bound columns by name. With the
     * bounds renamed to `left_bound` and `right_bound`, a name assembled from the wrong place
     * fails here.
     */
    #[Test]
    public function parentsByModelIdUsesTheConfiguredBoundColumns(): void
    {
        $nodes = $this->buildTree();

        $titles = CustomColumnsCategory::parentsByModelId($nodes['leaf']->getKey())
            ->get()
            ->pluck('title')
            ->all();

        static::assertSame(
            [
                'root',
                'branch',
            ],
            $titles
        );
    }
}
