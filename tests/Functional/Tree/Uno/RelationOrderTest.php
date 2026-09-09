<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * The `ancestors` and `descendants` relations must come back in tree order on every path — read
 * one node at a time or eager loaded for many.
 *
 * Both fixtures are built so that tree order differs from insertion order. Without that a
 * missing `ORDER BY` still looks right, because the rows arrive in the order they were written.
 */
class RelationOrderTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Children created c, a, b but positioned a, b, c.
     *
     * @return array<string, Category>
     */
    private function buildSiblings(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $c */
        $c = static::model(['title' => 'c']);
        $c->appendTo($root->refresh())->save();

        /** @var Category $a */
        $a = static::model(['title' => 'a']);
        $a->prependTo($root->refresh())->save();

        /** @var Category $b */
        $b = static::model(['title' => 'b']);
        $b->insertAfter($a->refresh())->save();

        return [
            'root' => $root->refresh(),
            'a'    => $a->refresh(),
            'b'    => $b->refresh(),
            'c'    => $c->refresh(),
        ];
    }

    /**
     * root -> mid -> upper -> lower -> leaf, where `mid` was created last and then had the
     * existing chain moved under it. Its key is therefore the highest while its position is the
     * shallowest.
     *
     * @return array<string, Category>
     */
    private function buildChain(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $upper */
        $upper = static::model(['title' => 'upper']);
        $upper->appendTo($root->refresh())->save();

        /** @var Category $lower */
        $lower = static::model(['title' => 'lower']);
        $lower->appendTo($upper->refresh())->save();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($lower->refresh())->save();

        /** @var Category $mid */
        $mid = static::model(['title' => 'mid']);
        $mid->appendTo($root->refresh())->save();

        $upper->refresh()->appendTo($mid->refresh())->save();

        return [
            'root'  => $root->refresh(),
            'mid'   => $mid->refresh(),
            'upper' => $upper->refresh(),
            'lower' => $lower->refresh(),
            'leaf'  => $leaf->refresh(),
        ];
    }

    /**
     * Guards the fixtures themselves. If key order ever lines up with tree order again, the
     * ordering tests below stop proving anything and this fails first.
     */
    #[Test]
    public function siblingKeyOrderDisagreesWithTreeOrder(): void
    {
        $nodes = $this->buildSiblings();

        static::assertLessThan($nodes['a']->getKey(), $nodes['c']->getKey());
        static::assertGreaterThan($nodes['a']->leftValue(), $nodes['c']->leftValue());
    }

    #[Test]
    public function ancestorKeyOrderDisagreesWithTreeOrder(): void
    {
        $nodes = $this->buildChain();

        static::assertGreaterThan($nodes['upper']->getKey(), $nodes['mid']->getKey());
        static::assertLessThan($nodes['upper']->leftValue(), $nodes['mid']->leftValue());
    }

    #[Test]
    public function lazyDescendantsComeBackInTreeOrder(): void
    {
        $nodes = $this->buildSiblings();

        static::assertSame(
            [
                'a',
                'b',
                'c',
            ],
            $nodes['root']->descendants()->get()->pluck('title')->all()
        );
    }

    #[Test]
    public function eagerDescendantsComeBackInTreeOrder(): void
    {
        $this->buildSiblings();

        $root = Category::query()->with('descendants')->get()->firstWhere('title', 'root');

        static::assertSame(
            [
                'a',
                'b',
                'c',
            ],
            $root->descendants->pluck('title')->all()
        );
    }

    #[Test]
    public function lazyAncestorsComeBackRootFirst(): void
    {
        $nodes = $this->buildChain();

        static::assertSame(
            [
                'root',
                'mid',
                'upper',
                'lower',
            ],
            $nodes['leaf']->ancestors()->get()->pluck('title')->all()
        );
    }

    #[Test]
    public function eagerAncestorsComeBackRootFirst(): void
    {
        $this->buildChain();

        $leaf = Category::query()->with('ancestors')->get()->firstWhere('title', 'leaf');

        static::assertSame(
            [
                'root',
                'mid',
                'upper',
                'lower',
            ],
            $leaf->ancestors->pluck('title')->all()
        );
    }

    /**
     * Asserted on the statement, not on the rows: a result that happens to arrive sorted proves
     * nothing about what was asked for.
     */
    #[Test]
    public function bothRelationQueriesCarryAnOrderBy(): void
    {
        $nodes = $this->buildChain();

        static::assertStringContainsString('order by', $nodes['root']->descendants()->toSql());
        static::assertStringContainsString('order by', $nodes['leaf']->ancestors()->toSql());
    }
}
