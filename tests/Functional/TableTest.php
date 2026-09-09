<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Table;
use Fureev\Trees\Tests\models\v5\Category;
use Illuminate\Console\BufferedConsoleOutput;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

class TableTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function drawFromModel(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($root)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 3.1']);
        $node22->appendTo($node21)->save();

        /** @var Category $node32 */
        $node32 = static::model(['title' => 'child 2.2']);
        $node32->appendTo($node1)->save();

        //

        $output = new BufferedConsoleOutput();
        Table::fromModel($root->refresh())
            ->setExtraColumns(
                ['title' => 'Label']
            )
            ->draw($output);

        $str = $output->fetch();

        self::assertNotEmpty($str);
    }

    /**
     * Backs docs/Console.md: fromTree() renders a collection that has already been linked, and
     * the level column can be dropped.
     */
    #[Test]
    public function drawFromTree(): void
    {
        $this->buildTree();

        $output = new BufferedConsoleOutput();

        Table::fromTree(Category::query()->defaultOrder()->get()->toTree())
            ->hideLevel()
            ->setExtraColumns(['title' => 'Label'])
            ->draw($output);

        $str = $output->fetch();

        static::assertStringContainsString('Label', $str);
        static::assertStringContainsString('root node', $str);
        static::assertStringContainsString('child 2.1', $str);
    }

    /**
     * Backs docs/Console.md: fromQuery() is an instance method, unlike the two static factories
     * beside it, so it is reached through `new Table()`.
     */
    #[Test]
    public function drawFromQuery(): void
    {
        $root = $this->buildTree();

        $output = new BufferedConsoleOutput();

        (new Table())
            ->fromQuery($root->newNestedSetQuery()->defaultOrder())
            ->setOffset('--')
            ->setExtraColumns(['title' => 'Label'])
            ->draw($output);

        $str = $output->fetch();

        static::assertStringContainsString('root node', $str);
        static::assertStringContainsString('child 2.1', $str);
    }

    private function buildTree(): Category
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($root)->save();

        /** @var Category $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21->refresh())->save();

        return $root->refresh();
    }

    /**
     * The form anyone writes by analogy with the two factories beside it. It used to be a fatal
     * error, and PHP refuses before any magic could soften it: a public non-static method in a
     * static context is not "inaccessible", so `__callStatic` never runs.
     */
    #[Test]
    public function theQueryFactoryIsStatic(): void
    {
        $root = $this->buildTree();

        $output = new BufferedConsoleOutput();

        Table::fromQuery($root->newNestedSetQuery()->defaultOrder())
            ->setExtraColumns(['title' => 'Label'])
            ->draw($output);

        $rendered = $output->fetch();

        static::assertStringContainsString('root node', $rendered);
        static::assertStringContainsString('child 2.1', $rendered);
    }

    /**
     * All three factories agree now. The mismatch was found by checking signatures while writing
     * the reference, so it is checked the same way here.
     */
    #[Test]
    public function everyFactoryIsStatic(): void
    {
        $reflection = new ReflectionClass(Table::class);

        $factories = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'from')) {
                $factories[$method->getName()] = $method->isStatic();
            }
        }

        ksort($factories);

        static::assertSame(
            [
                'fromModel' => true,
                'fromQuery' => true,
                'fromTree'  => true,
            ],
            $factories
        );
    }

    /**
     * Configuration goes after the factory, which is the only order the other two allow.
     */
    #[Test]
    public function configuringAfterTheFactoryApplies(): void
    {
        $root = $this->buildTree();

        $output = new BufferedConsoleOutput();

        Table::fromQuery($root->newNestedSetQuery()->defaultOrder())
            ->hideLevel()
            ->setExtraColumns(['title' => 'Label'])
            ->draw($output);

        $rendered = $output->fetch();

        static::assertStringNotContainsString('Level', $rendered);
        static::assertStringContainsString('Label', $rendered);
    }
}
