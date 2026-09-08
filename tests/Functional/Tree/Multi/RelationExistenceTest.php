<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Existence queries must stay inside a single tree: every root starts at lft = 1, so
 * bounds overlap across trees and a bounds-only condition would leak.
 */
class RelationExistenceTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    private function buildTree(string $rootTitle): MultiCategory
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => $rootTitle]);
        $root->makeRoot()->save();

        /** @var MultiCategory $node2 */
        $node2 = static::model(['title' => $rootTitle . ' / level 2']);
        $node2->appendTo($root)->save();

        return $root->refresh();
    }

    #[Test]
    public function hasDescendantsDoesNotLeakAcrossTrees(): void
    {
        $this->buildTree('tree 1');

        /** @var MultiCategory $lonely */
        $lonely = static::model(['title' => 'tree 2 root']);
        $lonely->makeRoot()->save();

        $branches = MultiCategory::query()->has('descendants')->get();

        static::assertCount(1, $branches);
        static::assertEquals('tree 1', $branches->first()->title);
        static::assertTrue($lonely->refresh()->isLeaf());
    }

    #[Test]
    public function withCountDescendantsIsScopedToOwnTree(): void
    {
        $root1 = $this->buildTree('tree 1');
        $this->buildTree('tree 2');

        /** @var MultiCategory $counted */
        $counted = MultiCategory::query()->withCount('descendants')->find($root1->getKey());

        static::assertEquals(1, $counted->descendants_count);
    }
}
