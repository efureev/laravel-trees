<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Healthy;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Healthy\DuplicatesCheck;
use Fureev\Trees\Healthy\HealthyChecker;
use Fureev\Trees\Healthy\MissingParentCheck;
use Fureev\Trees\Healthy\OddnessCheck;
use Fureev\Trees\Healthy\RangeCheck;
use Fureev\Trees\Healthy\RootCheck;
use Fureev\Trees\Healthy\WrongParentCheck;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\Functional\Helpers\TreeBuilder;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                'OddnessCheck'       => 0,
                'DuplicatesCheck'    => 0,
                'WrongParentCheck'   => 0,
                'MissingParentCheck' => 0,
                'RangeCheck'         => 0,
                'RootCheck'          => 0,
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
    /**
     * The count is a number of nodes. It used to be a number of ordered pairs, so two nodes
     * sharing a bound scored two only by coincidence and three sharing one scored six.
     */
    public function testDuplicatesCountsNodesRatherThanPairs(): void
    {
        $root     = TreeBuilder::from(self::modelClass())->build(3);
        $children = $this->children($root);

        static::assertSame(0, (new DuplicatesCheck($root))->check());

        $shared = $children->first()->leftValue();

        foreach ($children->skip(1) as $node) {
            $this->corrupt($node, [(string)$root->leftAttribute() => $shared]);
        }

        // Three nodes now share one left bound, and three is the answer.
        static::assertSame(3, (new DuplicatesCheck($root))->check());
    }

    /**
     * A level that does not sit one below the parent means something is between them — or that
     * the level itself is wrong. Either way the parent link no longer describes the tree. The
     * previous implementation looked for the intermediate row instead and saw neither.
     */
    public function testWrongParentDetectsABrokenLevel(): void
    {
        $root  = $this->buildTree();
        $child = $this->children($root)->first();

        static::assertSame(0, (new WrongParentCheck($root))->check());

        // Bounds untouched, so the parent still encloses the child.
        $this->corrupt($child, [(string)$root->levelAttribute() => 5]);

        static::assertSame(1, (new WrongParentCheck($root))->check());
    }

    /**
     * With only a root and one child there is no third row to find, which is all the previous
     * implementation could look for.
     */
    public function testWrongParentDetectsABrokenLinkInATreeOfTwo(): void
    {
        $root  = TreeBuilder::from(self::modelClass())->build(1);
        $child = $this->children($root)->first();

        static::assertSame(0, (new WrongParentCheck($root))->check());

        $this->corrupt(
            $child,
            [
                (string)$root->leftAttribute()  => 1000,
                (string)$root->rightAttribute() => 1001,
            ]
        );

        static::assertSame(1, (new WrongParentCheck($root))->check());
    }
    /**
     * A tree of N nodes uses the numbers 1..2N, so the outermost bound is twice the node count.
     * Deleting a subtree with a query takes the rows and leaves their numbers behind, so the
     * tree ends up wider than its contents — and every other check sees a perfectly nested one.
     */
    public function testRangeCheckDetectsAVacatedSpan(): void
    {
        $root  = $this->buildTree();
        $child = $this->children($root)->first();

        /** @var Category $grandchild */
        $grandchild = Category::make(['title' => 'grandchild']);
        $grandchild->appendTo($child->refresh())->save();

        static::assertSame(0, (new RangeCheck($root))->check());

        // Straight through the query builder, so none of the bookkeeping runs.
        $child->refresh()->newNestedSetQuery()->descendantsQuery()->getQuery()->delete();

        static::assertSame(0, (new OddnessCheck($root))->check());
        static::assertSame(0, (new DuplicatesCheck($root))->check());
        static::assertSame(0, (new WrongParentCheck($root))->check());
        static::assertSame(0, (new MissingParentCheck($root))->check());

        // Only this one notices.
        static::assertSame(1, (new RangeCheck($root))->check());
    }

    /**
     * Two roots whose bounds do not collide look sound to every check that compares bounds.
     */
    public function testRootCheckDetectsASecondRoot(): void
    {
        $root = $this->buildTree();

        static::assertSame(0, (new RootCheck($root))->check());

        $child = $this->children($root)->first();

        $this->corrupt($child, [(string)$root->parentAttribute() => null]);

        static::assertSame(1, (new RootCheck($root))->check());
    }

    /**
     * The check runs against the model it was handed, connection and all. Reducing it to a class
     * name and building it again dropped anything the caller had set.
     */
    public function testTheCheckStaysOnTheModelsConnection(): void
    {
        config(['database.connections.other' => config('database.connections.pgsql')]);

        $this->buildTree();

        $onOther = new Category();
        $onOther->setConnection('other');

        DB::connection('other')->enableQueryLog();
        DB::connection('other')->flushQueryLog();
        DB::connection()->flushQueryLog();

        (new HealthyChecker($onOther))->check();

        static::assertNotSame([], DB::connection('other')->getQueryLog());
        static::assertSame([], DB::connection()->getQueryLog());

        DB::purge('other');
    }
}
