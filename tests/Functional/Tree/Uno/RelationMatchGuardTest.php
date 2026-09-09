<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Relations\AncestorsRelation;
use Fureev\Trees\Relations\DescendantsRelation;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

/**
 * Both relations pair rows by comparing bounds, and both refuse to compare anything that is not
 * a node. Nothing reaches that guard through the public API — eager loading only ever produces
 * rows of the related model — so it is exercised directly. A guard nothing runs is a guard
 * nobody knows still holds.
 */
class RelationMatchGuardTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function neitherRelationPairsSomethingThatIsNotANode(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        $root = $root->refresh();

        $stranger = new NonTreeModel();

        foreach ([AncestorsRelation::class, DescendantsRelation::class] as $class) {
            /** @var AncestorsRelation|DescendantsRelation $relation */
            $relation = $class === AncestorsRelation::class
                ? $root->ancestors()
                : $root->descendants();

            $matches = new ReflectionMethod($class, 'matches');

            static::assertFalse($matches->invoke($relation, $stranger, $root), $class);
            static::assertFalse($matches->invoke($relation, $root, $stranger), $class);
        }
    }
}
