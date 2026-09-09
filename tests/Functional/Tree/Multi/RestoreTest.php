<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\ArchivedMultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Restoring across several trees. The helpers walk ancestors and descendants by comparing
 * bounds, and the identical chain in the neighbouring tree carries identical bounds — only the
 * tree condition keeps a restore from raising it too.
 */
class RestoreTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<ArchivedMultiCategory>
     */
    protected static function modelClass(): string
    {
        return ArchivedMultiCategory::class;
    }

    /**
     * Two trees of the same shape: root -> branch -> leaf, with the whole chain below the root
     * trashed in both.
     *
     * @return array<string, ArchivedMultiCategory>
     */
    private function buildTrashedTrees(): array
    {
        $nodes = [];

        foreach (['one', 'two'] as $name) {
            /** @var ArchivedMultiCategory $root */
            $root = static::model(['title' => "root $name"]);
            $root->save();

            /** @var ArchivedMultiCategory $branch */
            $branch = static::model(['title' => "branch $name"]);
            $branch->appendTo($root->refresh())->save();

            /** @var ArchivedMultiCategory $leaf */
            $leaf = static::model(['title' => "leaf $name"]);
            $leaf->appendTo($branch->refresh())->save();

            $nodes["root_$name"]   = $root->refresh();
            $nodes["branch_$name"] = $branch->refresh();
            $nodes["leaf_$name"]   = $leaf->refresh();
        }

        foreach (['one', 'two'] as $name) {
            $nodes["leaf_$name"]->delete();
            $nodes["branch_$name"]->refresh()->delete();
        }

        return $nodes;
    }

    /**
     * @return string[]
     */
    private function alive(): array
    {
        return ArchivedMultiCategory::query()
            ->orderBy('tree_id')
            ->orderBy('lft')
            ->get()
            ->pluck('title')
            ->all();
    }

    #[Test]
    public function theFixtureLeavesOnlyTheRootsAlive(): void
    {
        $nodes = $this->buildTrashedTrees();

        static::assertSame(
            [
                'root one',
                'root two',
            ],
            $this->alive()
        );

        // Same bounds in both trees, which is what makes the tests below mean something.
        static::assertSame(
            [
                $nodes['leaf_one']->leftValue(),
                $nodes['leaf_one']->rightValue(),
            ],
            [
                $nodes['leaf_two']->leftValue(),
                $nodes['leaf_two']->rightValue(),
            ]
        );
    }

    #[Test]
    public function plainRestoreBringsBackOnlyTheNode(): void
    {
        $nodes = $this->buildTrashedTrees();

        $nodes['leaf_one']->restore();

        static::assertSame(
            [
                'root one',
                'leaf one',
                'root two',
            ],
            $this->alive()
        );
    }

    #[Test]
    public function restoreWithParentsStaysInsideItsTree(): void
    {
        $nodes = $this->buildTrashedTrees();

        $nodes['leaf_one']->restoreWithParents();

        static::assertSame(
            [
                'root one',
                'branch one',
                'leaf one',
                'root two',
            ],
            $this->alive()
        );
    }

    #[Test]
    public function restoreWithDescendantsStaysInsideItsTree(): void
    {
        $nodes = $this->buildTrashedTrees();

        $nodes['branch_one']->restoreWithDescendants();

        static::assertSame(
            [
                'root one',
                'branch one',
                'leaf one',
                'root two',
            ],
            $this->alive()
        );
    }

    /**
     * The cutoff narrows a restore to what was trashed at or after that moment. Passing the
     * node's own timestamp lets everything trashed with it through.
     */
    #[Test]
    public function restoreRespectsTheCutoff(): void
    {
        $nodes = $this->buildTrashedTrees();

        $trashedAt = (string)ArchivedMultiCategory::withTrashed()
            ->whereKey($nodes['leaf_one']->getKey())
            ->first()
            ->deleted_at;

        $nodes['leaf_one']->restoreWithParents($trashedAt);

        static::assertContains('leaf one', $this->alive());
        static::assertNotContains('branch two', $this->alive());
    }

    #[Test]
    public function theTreesAreHealthyAfterRestoring(): void
    {
        $nodes = $this->buildTrashedTrees();

        $nodes['leaf_one']->restoreWithParents();
        $nodes['branch_two']->restoreWithDescendants();

        static::assertFalse((new HealthyChecker(ArchivedMultiCategory::class))->isBroken());

        static::assertSame(
            [
                'root one',
                'branch one',
                'leaf one',
                'root two',
                'branch two',
                'leaf two',
            ],
            $this->alive()
        );
    }
}
