<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Collection;
use Fureev\Trees\Table;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use Illuminate\Console\BufferedConsoleOutput;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the renderer does with input that is not the tidy case: column names given as a plain
 * list, a node whose `children` relation is not a collection, and something in the collection
 * that is not a node at all.
 */
class TableEdgeCasesTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    private function buildTree(): Category
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root->refresh())->save();

        return $root->refresh();
    }

    /**
     * Given a list rather than a map, the column name doubles as its own label.
     */
    #[Test]
    public function aPlainListOfColumnsLabelsItself(): void
    {
        $root = $this->buildTree();

        $output = new BufferedConsoleOutput();

        Table::fromModel($root)
            ->setExtraColumns(['title'])
            ->draw($output);

        $rendered = $output->fetch();

        static::assertStringContainsString('title', $rendered);
        static::assertStringContainsString('root node', $rendered);
    }

    /**
     * `fromTree()` takes whatever collection it is given, so a node whose `children` was set to
     * something else still has to render.
     */
    #[Test]
    public function aNodeWithoutAChildrenCollectionStillRenders(): void
    {
        $root = $this->buildTree();

        $root->setRelation('children', null);

        $output = new BufferedConsoleOutput();

        Table::fromTree(new Collection([$root]))
            ->setExtraColumns(['title' => 'Label'])
            ->draw($output);

        static::assertStringContainsString('root node', $output->fetch());
    }

    #[Test]
    public function somethingThatIsNotANodeIsSkipped(): void
    {
        $root = $this->buildTree();
        $root->setRelation('children', new Collection());

        $output = new BufferedConsoleOutput();

        Table::fromTree(new Collection([new NonTreeModel(), $root]))
            ->setExtraColumns(['title' => 'Label'])
            ->draw($output);

        $rendered = $output->fetch();

        static::assertStringContainsString('root node', $rendered);
    }
    /**
     * Column names used to be memoised into a `static` inside the method, so the second table
     * built in one process rendered the first one's columns under its own headers.
     */
    #[Test]
    public function eachTableRendersItsOwnColumns(): void
    {
        $root = $this->buildTree();

        $first = new BufferedConsoleOutput();
        Table::fromModel($root)->setExtraColumns(['title' => 'Label'])->draw($first);

        $second = new BufferedConsoleOutput();
        Table::fromModel($root->refresh())->setExtraColumns(['lvl' => 'Depth'])->draw($second);

        static::assertStringContainsString('root node', $first->fetch());

        $rendered = $second->fetch();

        static::assertStringContainsString('Depth', $rendered);
        static::assertStringNotContainsString('root node', $rendered);
    }
}
