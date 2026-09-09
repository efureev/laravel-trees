<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\VetoCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Eloquent lets a listener abort by returning false from `deleting` or `restoring`. The package
 * checks for that in three places and answers false instead of proceeding.
 */
class VetoedEventsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<VetoCategory>
     */
    protected static function modelClass(): string
    {
        return VetoCategory::class;
    }

    public function setUp(): void
    {
        parent::setUp();

        VetoCategory::$veto = false;
    }

    protected function tearDown(): void
    {
        VetoCategory::$veto = false;

        parent::tearDown();
    }

    /**
     * @return array<string, VetoCategory>
     */
    private function buildTree(): array
    {
        /** @var VetoCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var VetoCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var VetoCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
        ];
    }

    #[Test]
    public function aVetoedDeletingEventStopsDeleteWithChildren(): void
    {
        $nodes = $this->buildTree();

        VetoCategory::$veto = true;

        static::assertFalse($nodes['branch']->deleteWithChildren());
        static::assertSame(3, VetoCategory::query()->count());
    }

    #[Test]
    public function aVetoedRestoringEventStopsBothRestoreHelpers(): void
    {
        $nodes = $this->buildTree();

        $nodes['leaf']->delete();

        VetoCategory::$veto = true;

        static::assertFalse($nodes['leaf']->restoreWithParents());
        static::assertFalse($nodes['leaf']->restoreWithDescendants());

        static::assertSame(2, VetoCategory::query()->count());
    }

    /**
     * The `$deletedAt` argument narrows a restore to what was trashed at or after that moment,
     * so an older deletion stays trashed.
     */
    #[Test]
    public function restoreCanBeLimitedByDeletionTime(): void
    {
        $nodes = $this->buildTree();

        $nodes['leaf']->delete();

        $cutoff = (string)VetoCategory::withTrashed()
            ->whereKey($nodes['leaf']->getKey())
            ->first()
            ->deleted_at;

        static::assertSame(2, VetoCategory::query()->count());

        $nodes['leaf']->restoreWithParents($cutoff);

        static::assertSame(3, VetoCategory::query()->count());
    }

    #[Test]
    public function restoreWithDescendantsAcceptsTheSameCutoff(): void
    {
        $nodes = $this->buildTree();

        $nodes['leaf']->delete();

        $cutoff = (string)VetoCategory::withTrashed()
            ->whereKey($nodes['leaf']->getKey())
            ->first()
            ->deleted_at;

        $nodes['leaf']->restoreWithDescendants($cutoff);

        static::assertSame(3, VetoCategory::query()->count());
    }

    /**
     * `isLeaf()` on a soft-deleting model answers from the `children` relation when it is
     * already loaded, instead of counting again.
     */
    #[Test]
    public function isLeafReadsTheLoadedChildrenRelation(): void
    {
        $nodes = $this->buildTree();

        $loaded = VetoCategory::query()->with('children')->get()->keyBy('title');

        static::assertTrue($loaded->get('leaf')->relationLoaded('children'));
        static::assertTrue($loaded->get('leaf')->isLeaf());
        static::assertFalse($loaded->get('branch')->isLeaf());
    }

    /**
     * `parentWithTrashed()` differs from `parent` only on a soft-deleting model: it keeps a
     * trashed parent visible.
     */
    #[Test]
    public function parentWithTrashedSeesATrashedParent(): void
    {
        $nodes = $this->buildTree();

        $nodes['branch']->delete();

        $leaf = VetoCategory::query()->whereKey($nodes['leaf']->getKey())->first();

        static::assertNull($leaf->parent()->first());
        static::assertSame('branch', $leaf->parentWithTrashed()->first()->title);
    }
    /**
     * `forceSave()` raises a flag that makes an unchanged model save anyway. A listener vetoing
     * `saving` returns from `save()` before any of the package's own listeners run, so the flag
     * has to be lowered by `forceSave()` itself — otherwise it stays raised and every later
     * plain `save()` writes whether or not anything changed.
     */
    #[Test]
    public function aVetoedSaveDoesNotLeaveForceSaveRaised(): void
    {
        $nodes = $this->buildTree();

        VetoCategory::saving(static fn() => false);

        $leaf = $nodes['leaf'];

        static::assertFalse($leaf->forceSave());
        static::assertFalse($leaf->isForceSaving());

        // And an ordinary save of an unchanged model is still skipped, rather than forced by a
        // flag left over from the call before.
        static::assertSame([], $leaf->getDirty());
    }
}
