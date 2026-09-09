<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use PHPUnit\Framework\Attributes\Test;

/**
 * `isChildOf()` compares bounds, so it answers "is a descendant", not "is a child". These pin
 * that down and cover the two names that say what they mean: `isDescendantOf()` for the same
 * question, `isDirectChildOf()` for the stricter one the old name suggests.
 */
class IsChildOfTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * root -> branch -> leaf, with `aside` a second child of the root.
     *
     * @return array<string, Category>
     */
    private function buildTree(): array
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var Category $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var Category $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        /** @var Category $aside */
        $aside = static::model(['title' => 'aside']);
        $aside->appendTo($root->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
            'aside'  => $aside->refresh(),
        ];
    }

    #[Test]
    public function isDescendantOfAnswersExactlyAsIsChildOf(): void
    {
        $nodes = $this->buildTree();

        foreach ($nodes as $fromName => $from) {
            foreach ($nodes as $toName => $to) {
                static::assertSame(
                    $from->isChildOf($to),
                    $from->isDescendantOf($to),
                    "$fromName vs $toName"
                );
            }
        }
    }

    /**
     * The distinction the old name hides: one level down is true for both methods, two levels
     * down only for the bounds check.
     */
    #[Test]
    public function isDirectChildOfIsTrueOnlyOneLevelDown(): void
    {
        $nodes = $this->buildTree();

        static::assertTrue($nodes['leaf']->isDirectChildOf($nodes['branch']));
        static::assertTrue($nodes['leaf']->isDescendantOf($nodes['branch']));

        static::assertFalse($nodes['leaf']->isDirectChildOf($nodes['root']));
        static::assertTrue($nodes['leaf']->isDescendantOf($nodes['root']));
    }

    #[Test]
    public function aRootIsNobodysChild(): void
    {
        $nodes = $this->buildTree();

        static::assertNull($nodes['root']->parentValue());

        foreach ($nodes as $name => $node) {
            static::assertFalse($nodes['root']->isDirectChildOf($node), "root vs $name");
            static::assertFalse($nodes['root']->isDescendantOf($node), "root vs $name");
        }
    }

    #[Test]
    public function aNodeIsNotItsOwnDescendant(): void
    {
        $nodes = $this->buildTree();

        foreach ($nodes as $name => $node) {
            static::assertFalse($node->isChildOf($node), $name);
            static::assertFalse($node->isDescendantOf($node), $name);
            static::assertFalse($node->isDirectChildOf($node), $name);
        }
    }

    #[Test]
    public function siblingsAreUnrelated(): void
    {
        $nodes = $this->buildTree();

        static::assertSame($nodes['branch']->parentValue(), $nodes['aside']->parentValue());

        static::assertFalse($nodes['branch']->isDescendantOf($nodes['aside']));
        static::assertFalse($nodes['aside']->isDescendantOf($nodes['branch']));
        static::assertFalse($nodes['aside']->isDirectChildOf($nodes['branch']));
    }

    /**
     * The relation runs the other way round, and neither method is symmetric.
     */
    #[Test]
    public function aParentIsNotAChildOfItsOwnChild(): void
    {
        $nodes = $this->buildTree();

        static::assertFalse($nodes['root']->isDescendantOf($nodes['leaf']));
        static::assertFalse($nodes['branch']->isDirectChildOf($nodes['leaf']));
    }
}
