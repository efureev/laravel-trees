<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * fillMissingIntermediateNodes() used to run one query per node whose parent was absent.
 */
class CollectionQueryCountTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> (a -> a1 -> a2, b -> b1 -> b2)
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        $nodes = ['root' => $root];

        foreach (['a', 'b'] as $branch) {
            $parent = $root->refresh();

            foreach ([$branch, "{$branch}1", "{$branch}2"] as $title) {
                /** @var Category $node */
                $node = static::model(['title' => $title]);
                $node->appendTo($parent)->save();
                $nodes[$title] = $node->refresh();
                $parent        = $nodes[$title];
            }
        }

        return $nodes;
    }

    /**
     * @return string[]
     */
    private function selects(): array
    {
        return array_values(
            array_filter(
                array_column(static::model()->getConnection()->getQueryLog(), 'query'),
                static fn(string $sql) => str_starts_with(strtolower($sql), 'select')
            )
        );
    }

    #[Test]
    public function fillsMissingParentsInASingleQuery(): void
    {
        $nodes = $this->buildTree();

        // Two deep leaves from two different branches: each is missing its own chain.
        $collection = new Collection(
            [
                $nodes['a2'],
                $nodes['b2'],
            ]
        );

        static::model()->getConnection()->flushQueryLog();

        $collection->fillMissingIntermediateNodes();

        static::assertCount(
            1,
            $this->selects(),
            'the missing ancestors of every node fit into one query'
        );

        // root, a, a1, a2, b, b1, b2
        static::assertCount(7, $collection);
    }

    #[Test]
    public function asksNothingWhenNoParentsAreMissing(): void
    {
        $nodes = $this->buildTree();

        $collection = new Collection(
            [
                $nodes['root'],
                $nodes['a'],
            ]
        );

        static::model()->getConnection()->flushQueryLog();

        $collection->fillMissingIntermediateNodes();

        static::assertSame([], $this->selects());
        static::assertCount(2, $collection);
    }
}
