<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\ArchivedCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Backs docs/SoftDeletes.md. The restore helpers had no coverage at all, so nothing could be
 * written about them without first pinning what they do.
 */
class RestoreTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<ArchivedCategory>
     */
    protected static function modelClass(): string
    {
        return ArchivedCategory::class;
    }

    /**
     * root -> mid -> sub -> leaf. The root stays alive throughout: a single tree refuses to
     * delete its root at all.
     *
     * @return array<string, ArchivedCategory>
     */
    private function buildChain(): array
    {
        /** @var ArchivedCategory $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var ArchivedCategory $mid */
        $mid = static::model(['title' => 'mid']);
        $mid->appendTo($root)->save();

        /** @var ArchivedCategory $sub */
        $sub = static::model(['title' => 'sub']);
        $sub->appendTo($mid->refresh())->save();

        /** @var ArchivedCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($sub->refresh())->save();

        return [
            'root' => $root->refresh(),
            'mid'  => $mid->refresh(),
            'sub'  => $sub->refresh(),
            'leaf' => $leaf->refresh(),
        ];
    }

    /**
     * Backs docs/SoftDeletes.md: a plain restore brings back that node alone, and its place in
     * the tree is exactly where it was, because soft deleting never renumbered anything.
     */
    #[Test]
    public function plainRestoreBringsBackOnlyTheNode(): void
    {
        $nodes  = $this->buildChain();
        $bounds = [
            $nodes['leaf']->leftValue(),
            $nodes['leaf']->rightValue(),
        ];

        $nodes['leaf']->delete();
        static::assertSame(3, ArchivedCategory::query()->count());

        ArchivedCategory::withTrashed()->find($nodes['leaf']->getKey())->restore();

        $restored = $nodes['leaf']->refresh();

        static::assertSame(4, ArchivedCategory::query()->count());
        static::assertSame(
            $bounds,
            [
                $restored->leftValue(),
                $restored->rightValue(),
            ]
        );
        static::assertSame($nodes['sub']->getKey(), $restored->parentValue());
    }

    /**
     * Backs docs/SoftDeletes.md: restoreWithParents() brings the node back together with every
     * ancestor above it, so it is reachable from the root again.
     */
    #[Test]
    public function restoreWithParentsBringsBackTheWholeChain(): void
    {
        $nodes = $this->buildChain();

        $nodes['leaf']->delete();
        $nodes['sub']->refresh()->delete();
        $nodes['mid']->refresh()->delete();

        // Only the root is left standing.
        static::assertSame(1, ArchivedCategory::query()->count());

        ArchivedCategory::withTrashed()
            ->find($nodes['leaf']->getKey())
            ->restoreWithParents();

        // The node and every ancestor above it are back.
        static::assertSame(4, ArchivedCategory::query()->count());
    }

    /**
     * Backs docs/SoftDeletes.md: restoreWithDescendants() brings the node back together with
     * everything beneath it.
     */
    #[Test]
    public function restoreWithDescendantsBringsBackTheSubtree(): void
    {
        $nodes = $this->buildChain();

        $nodes['leaf']->delete();
        $nodes['sub']->refresh()->delete();

        static::assertSame(2, ArchivedCategory::query()->count());

        ArchivedCategory::withTrashed()
            ->find($nodes['sub']->getKey())
            ->restoreWithDescendants();

        // The node and everything beneath it are back.
        static::assertSame(4, ArchivedCategory::query()->count());
    }
}
