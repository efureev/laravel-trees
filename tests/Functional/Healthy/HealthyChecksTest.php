<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Healthy;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Healthy\DuplicatesCheck;
use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Healthy\MissingParentCheck;
use Fureev\Trees\Healthy\OddnessCheck;
use Fureev\Trees\Healthy\WrongParentCheck;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\TreeBuilder;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use Illuminate\Support\Collection;

/**
 * Covers detection of broken trees by the Healthy checks (category A).
 *
 * Each test builds a valid tree, asserts the check reports zero errors, then
 * corrupts the tree directly in the database (bypassing the nested-set logic)
 * and asserts the check detects the breakage.
 */
class HealthyChecksTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Builds a healthy tree: a root with two leaf children.
     */
    private function buildTree(): Category
    {
        return TreeBuilder::from(self::modelClass())->build(2);
    }

    /**
     * @return Collection<int, Category>
     */
    private function children(Category $root): Collection
    {
        return Category::query()
            ->where((string)$root->parentAttribute(), $root->getKey())
            ->orderBy((string)$root->leftAttribute())
            ->get();
    }

    /**
     * Updates the row directly, bypassing the nested-set bookkeeping,
     * so the tree becomes inconsistent on purpose.
     *
     * @param array<string, mixed> $values
     */
    private function corrupt(Category $node, array $values): void
    {
        $node->getConnection()
            ->table($node->getTable())
            ->where($node->getKeyName(), $node->getKey())
            ->update($values);
    }

    public function testOddnessCheckDetectsBrokenBounds(): void
    {
        $root  = $this->buildTree();
        $child = $this->children($root)->first();

        static::assertSame(0, (new OddnessCheck($root))->check());

        // Make `rgt` equal to `lft` => `lft >= rgt` and `(rgt - lft) % 2 = 0`.
        $this->corrupt($child, [(string)$root->rightAttribute() => $child->leftValue()]);

        static::assertGreaterThan(0, (new OddnessCheck($root))->check());
    }

    public function testDuplicatesCheckDetectsCoincidingBounds(): void
    {
        $root     = $this->buildTree();
        $children = $this->children($root);
        $first    = $children->first();
        $second   = $children->last();

        static::assertSame(0, (new DuplicatesCheck($root))->check());

        // Make the second child share the `lft` boundary with the first one.
        $this->corrupt($second, [(string)$root->leftAttribute() => $first->leftValue()]);

        static::assertGreaterThan(0, (new DuplicatesCheck($root))->check());
    }

    public function testWrongParentCheckDetectsNodeOutsideParentBounds(): void
    {
        $root  = $this->buildTree();
        $child = $this->children($root)->first();

        static::assertSame(0, (new WrongParentCheck($root))->check());

        // Move the child bounds outside the root range while keeping its parent_id.
        $this->corrupt(
            $child,
            [
                (string)$root->leftAttribute()  => 1000,
                (string)$root->rightAttribute() => 1001,
            ]
        );

        static::assertGreaterThan(0, (new WrongParentCheck($root))->check());
    }

    public function testMissingParentCheckDetectsOrphanNode(): void
    {
        $root  = $this->buildTree();
        $child = $this->children($root)->first();

        static::assertSame(0, (new MissingParentCheck($root))->check());

        // Point the child to a non-existent parent.
        $this->corrupt($child, [(string)$root->parentAttribute() => 999999]);

        static::assertGreaterThan(0, (new MissingParentCheck($root))->check());
    }

    public function testHealthyCheckerReportsNoErrorsForHealthyTree(): void
    {
        $root    = $this->buildTree();
        $checker = new HealthyChecker($root);

        static::assertSame(
            [
                'OddnessCheck'     => 0,
                'DuplicatesCheck'  => 0,
                'WrongParentCheck' => 0,
            ],
            $checker->check()
        );
        static::assertSame(0, $checker->getTotalErrors());
        static::assertFalse($checker->isBroken());
    }

    public function testHealthyCheckerDetectsBrokenTree(): void
    {
        $root  = $this->buildTree();
        $child = $this->children($root)->first();

        $this->corrupt($child, [(string)$root->rightAttribute() => $child->leftValue()]);

        $checker = new HealthyChecker($root);

        static::assertTrue($checker->isBroken());
        static::assertGreaterThan(0, $checker->getTotalErrors());
    }

    public function testCheckRejectsNonTreeModel(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model should be a Tree Node');

        new OddnessCheck(NonTreeModel::class);
    }
}
