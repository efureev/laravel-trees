<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Both relations are bounds-based, and every tree starts at lft = 1, so two trees of the
 * same shape carry identical bounds. Nothing but the tree column keeps them apart.
 */
class RelationIsolationTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * root -> node2 -> node3, titles prefixed so trees stay distinguishable.
     *
     * @return array<string, MultiCategory>
     */
    private function buildTree(string $prefix): array
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => "$prefix root"]);
        $root->makeRoot()->save();

        /** @var MultiCategory $node2 */
        $node2 = static::model(['title' => "$prefix level 2"]);
        $node2->appendTo($root)->save();

        /** @var MultiCategory $node3 */
        $node3 = static::model(['title' => "$prefix level 3"]);
        $node3->appendTo($node2)->save();

        return [
            'root'  => $root->refresh(),
            'node2' => $node2->refresh(),
            'node3' => $node3->refresh(),
        ];
    }

    #[Test]
    public function twoTreesReallyShareTheSameBounds(): void
    {
        $one = $this->buildTree('one');
        $two = $this->buildTree('two');

        // Guards the premise of every other test in this class.
        static::assertSame($one['root']->leftValue(), $two['root']->leftValue());
        static::assertSame($one['root']->rightValue(), $two['root']->rightValue());
        static::assertNotSame($one['root']->treeValue(), $two['root']->treeValue());
    }

    #[Test]
    public function descendantsStayInsideTheirOwnTree(): void
    {
        $one = $this->buildTree('one');
        $this->buildTree('two');

        $titles = $one['root']->descendants()->get()->pluck('title')->all();

        static::assertCount(2, $titles);
        foreach ($titles as $title) {
            static::assertStringStartsWith('one ', $title);
        }
    }

    #[Test]
    public function ancestorsStayInsideTheirOwnTree(): void
    {
        $one = $this->buildTree('one');
        $this->buildTree('two');

        $titles = $one['node3']->ancestors()->get()->pluck('title')->all();

        static::assertCount(2, $titles);
        foreach ($titles as $title) {
            static::assertStringStartsWith('one ', $title);
        }
    }

    #[Test]
    public function propertyAccessStaysInsideTheirOwnTree(): void
    {
        $one = $this->buildTree('one');
        $this->buildTree('two');

        /** @var MultiCategory $node3 */
        $node3 = MultiCategory::query()->find($one['node3']->getKey());

        static::assertCount(2, $node3->ancestors);
        static::assertCount(0, $node3->descendants);
    }

    #[Test]
    public function eagerLoadingPairsEveryNodeWithItsOwnTree(): void
    {
        $this->buildTree('one');
        $this->buildTree('two');

        $nodes = MultiCategory::query()->with('descendants', 'ancestors')->get();

        static::assertCount(6, $nodes);

        foreach ($nodes as $node) {
            $prefix = explode(' ', $node->title)[0];

            foreach ($node->getRelation('descendants') as $related) {
                static::assertStringStartsWith("$prefix ", $related->title);
                static::assertSame($node->treeValue(), $related->treeValue());
            }

            foreach ($node->getRelation('ancestors') as $related) {
                static::assertStringStartsWith("$prefix ", $related->title);
                static::assertSame($node->treeValue(), $related->treeValue());
            }
        }
    }

    #[Test]
    public function eagerLoadingKeepsTheSameCountsAsLazyLoading(): void
    {
        $one = $this->buildTree('one');
        $this->buildTree('two');

        /** @var MultiCategory $eager */
        $eager = MultiCategory::query()
            ->with('descendants', 'ancestors')
            ->find($one['node2']->getKey());

        static::assertCount($one['node2']->descendants()->count(), $eager->getRelation('descendants'));
        static::assertCount($one['node2']->ancestors()->count(), $eager->getRelation('ancestors'));
    }
}
