<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class PrependTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function prepend(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        $treeId = $modelRoot->tree_id;
        static::assertNotNull($treeId);

        // Level 2
        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->prependTo($modelRoot)->save();
        $modelRoot->refresh();


        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(4, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(2, $node21->leftValue());
        static::assertEquals(3, $node21->rightValue());
        static::assertEquals($treeId, $node21->treeValue());

        static::assertCount(1, $node21->parents());

        $_root = $node21->parent()->first();

        static::assertTrue($_root->isRoot());
        static::assertTrue($modelRoot->isEqualTo($_root));


        // Level 3
        /** @var MultiCategory $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->prependTo($modelRoot)->save();

        $node21->refresh();
        $modelRoot->refresh();

        static::assertSame(0, $modelRoot->levelValue());
        static::assertEquals(1, $modelRoot->leftValue());
        static::assertEquals(6, $modelRoot->rightValue());

        static::assertSame(1, $node21->levelValue());
        static::assertEquals(4, $node21->leftValue());
        static::assertEquals(5, $node21->rightValue());

        static::assertSame(1, $node31->levelValue());
        static::assertEquals(2, $node31->leftValue());
        static::assertEquals(3, $node31->rightValue());

        static::assertEquals($treeId, $node31->treeValue());
        static::assertCount(1, $node31->parents());

        $_root = $node31->getRoot();

        static::assertTrue($_root->isRoot());
        static::assertTrue($modelRoot->isEqualTo($_root));
    }


    #[Test]
    public function prependIntoSeveralTree(): void
    {
        for ($i = 0; $i < 3; $i++) {
            /** @var MultiCategory $modelRoot */
            $modelRoot = static::model(['title' => 'root node']);
            $modelRoot->save();

            $treeId = $modelRoot->tree_id;
            static::assertNotNull($treeId);

            // Level 2
            /** @var MultiCategory $node21 */
            $node21 = static::model(['title' => 'child 2.1']);
            $node21->prependTo($modelRoot)->save();
            $modelRoot->refresh();


            static::assertSame(0, $modelRoot->levelValue());
            static::assertEquals(1, $modelRoot->leftValue());
            static::assertEquals(4, $modelRoot->rightValue());

            static::assertSame(1, $node21->levelValue());
            static::assertEquals(2, $node21->leftValue());
            static::assertEquals(3, $node21->rightValue());
            static::assertEquals($treeId, $node21->treeValue());
            static::assertCount(1, $node21->parents());

            $_root = $node21->parent()->first();

            static::assertTrue($_root->isRoot());
            static::assertTrue($modelRoot->isEqualTo($_root));


            // Level 3
            /** @var MultiCategory $node31 */
            $node31 = static::model(['title' => 'child 3.1']);
            $node31->prependTo($modelRoot)->save();

            $node21->refresh();
            $modelRoot->refresh();

            static::assertSame(0, $modelRoot->levelValue());
            static::assertEquals(1, $modelRoot->leftValue());
            static::assertEquals(6, $modelRoot->rightValue());

            static::assertSame(1, $node21->levelValue());
            static::assertEquals(4, $node21->leftValue());
            static::assertEquals(5, $node21->rightValue());

            static::assertSame(1, $node31->levelValue());
            static::assertEquals(2, $node31->leftValue());
            static::assertEquals(3, $node31->rightValue());
            static::assertCount(1, $node31->parents());

            static::assertEquals($treeId, $node31->treeValue());

            $_root = $node31->getRoot();

            static::assertTrue($_root->isRoot());
            static::assertTrue($modelRoot->isEqualTo($_root));
        }

        static::assertCount(3, MultiCategory::root()->get()->unique->tree_id);
    }


    #[Test]
    public function appendToSameException(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        $this->expectException(Exception::class);

        $modelRoot->prependTo($modelRoot)->save();
    }

    #[Test]
    public function appendToNonExistParentException(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);

        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);

        $this->expectException(Exception::class);
        $node21->prependTo($modelRoot)->save();
    }
}
