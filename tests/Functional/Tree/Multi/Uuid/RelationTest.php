<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\Uuid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithUuid;
use PHPUnit\Framework\Attributes\Test;

/**
 * The relations pair nodes through isChildOf(), which compares tree values strictly.
 * With uuid primary keys and a uuid tree column both sides are strings, so this suite
 * guards the string-key half of that comparison.
 */
class RelationTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategoryWithUuid>
     */
    protected static function modelClass(): string
    {
        return MultiCategoryWithUuid::class;
    }

    /**
     * @return array<string, MultiCategoryWithUuid>
     */
    private function buildTree(string $prefix): array
    {
        /** @var MultiCategoryWithUuid $root */
        $root = static::model(['title' => "$prefix root"]);
        $root->makeRoot()->save();

        /** @var MultiCategoryWithUuid $node2 */
        $node2 = static::model(['title' => "$prefix level 2"]);
        $node2->appendTo($root)->save();

        /** @var MultiCategoryWithUuid $node3 */
        $node3 = static::model(['title' => "$prefix level 3"]);
        $node3->appendTo($node2)->save();

        return [
            'root'  => $root->refresh(),
            'node2' => $node2->refresh(),
            'node3' => $node3->refresh(),
        ];
    }

    #[Test]
    public function relationsWorkWithUuidKeys(): void
    {
        $nodes = $this->buildTree('one');

        static::assertIsString($nodes['root']->getKey());
        static::assertIsString($nodes['root']->treeValue());

        static::assertEquals(2, $nodes['root']->descendants()->count());
        static::assertEquals(2, $nodes['node3']->ancestors()->count());
        static::assertEquals(0, $nodes['root']->ancestors()->count());
        static::assertEquals(0, $nodes['node3']->descendants()->count());
    }

    #[Test]
    public function relationsStayInsideTheirOwnTree(): void
    {
        $one = $this->buildTree('one');
        $this->buildTree('two');

        foreach ($one['root']->descendants()->get() as $node) {
            static::assertStringStartsWith('one ', $node->title);
        }

        foreach ($one['node3']->ancestors()->get() as $node) {
            static::assertStringStartsWith('one ', $node->title);
        }
    }

    #[Test]
    public function eagerLoadingPairsNodesWithUuidTreeValues(): void
    {
        $this->buildTree('one');
        $this->buildTree('two');

        $nodes = MultiCategoryWithUuid::query()->with('descendants', 'ancestors')->get();

        static::assertCount(6, $nodes);

        foreach ($nodes as $node) {
            $prefix = explode(' ', $node->title)[0];

            foreach ($node->getRelation('descendants') as $related) {
                static::assertStringStartsWith("$prefix ", $related->title);
            }

            foreach ($node->getRelation('ancestors') as $related) {
                static::assertStringStartsWith("$prefix ", $related->title);
            }
        }
    }

    #[Test]
    public function existenceQueriesWorkWithUuidKeys(): void
    {
        $this->buildTree('one');
        $this->buildTree('two');

        static::assertEquals(4, MultiCategoryWithUuid::query()->has('descendants')->count());
        static::assertEquals(4, MultiCategoryWithUuid::query()->has('ancestors')->count());
        static::assertEquals(2, MultiCategoryWithUuid::query()->doesntHave('descendants')->count());
    }
}
