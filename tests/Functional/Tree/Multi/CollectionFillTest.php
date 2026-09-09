<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * The ancestors of every orphan are fetched in one query, so the tree condition has to sit
 * inside each OR group — the orphans may well come from different trees, whose bounds overlap.
 */
class CollectionFillTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    /**
     * @return array<string, MultiCategory>
     */
    private function buildTree(string $prefix): array
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => "$prefix root"]);
        $root->makeRoot()->save();

        /** @var MultiCategory $mid */
        $mid = static::model(['title' => "$prefix mid"]);
        $mid->appendTo($root)->save();

        /** @var MultiCategory $leaf */
        $leaf = static::model(['title' => "$prefix leaf"]);
        $leaf->appendTo($mid->refresh())->save();

        return [
            'root' => $root->refresh(),
            'mid'  => $mid->refresh(),
            'leaf' => $leaf->refresh(),
        ];
    }

    #[Test]
    public function fillsMissingParentsWithinEachOwnTree(): void
    {
        $one = $this->buildTree('one');
        $two = $this->buildTree('two');

        $collection = new Collection(
            [
                $one['leaf'],
                $two['leaf'],
            ]
        );

        $collection->fillMissingIntermediateNodes();

        // Two leaves plus two ancestors each, and nothing from the other tree.
        static::assertCount(6, $collection);

        $byTree = $collection->groupBy(static fn(MultiCategory $node) => (string)$node->treeValue());

        static::assertCount(2, $byTree);
        foreach ($byTree as $nodes) {
            $prefixes = $nodes->map(static fn(MultiCategory $node) => explode(' ', $node->title)[0])->unique();
            static::assertCount(1, $prefixes, 'a tree must not pick up nodes from its neighbour');
        }
    }
}
