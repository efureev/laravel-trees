<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\QueryBuilder;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use PHPUnit\Framework\Attributes\Test;

class ParentsByModelIdTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function basic(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        $treeId = $modelRoot->tree_id;
        static::assertNotNull($treeId);

        // Level 2
        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();
        $modelRoot->refresh();

        // Level 3
        /** @var MultiCategory $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();
        $modelRoot->refresh();

        $collection = MultiCategory::parentsByModelId($node31->id)->get();

        self::assertCount(2, $collection);
    }

    #[Test]
    public function andSelf(): void
    {
        /** @var MultiCategory $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        $treeId = $modelRoot->tree_id;
        static::assertNotNull($treeId);

        // Level 2
        /** @var MultiCategory $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();
        $modelRoot->refresh();

        // Level 3
        /** @var MultiCategory $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();
        $modelRoot->refresh();

        ///

        /** @var MultiCategory $modelRoot2 */
        $modelRoot2 = static::model(['title' => 'root node 2']);
        $modelRoot2->save();

        // Level 2
        /** @var MultiCategory $node221 */
        $node221 = static::model(['title' => 'child 22.1']);
        $node221->appendTo($modelRoot2)->save();
        $modelRoot2->refresh();

        // Level 3
        /** @var MultiCategory $node231 */
        $node231 = static::model(['title' => 'child 23.1']);
        $node231->appendTo($node221)->save();
        $modelRoot2->refresh();

        $collection = MultiCategory::parentsByModelId($node31->id, andSelf: true)->get();
        self::assertCount(3, $collection);

        $collection = MultiCategory::parentsByModelId($node31->id, 2)->get();
        self::assertCount(0, $collection);

        $collection = MultiCategory::parentsByModelId($node31->id, 1)->get();
        self::assertCount(1, $collection);

        $collection = MultiCategory::parentsByModelId($node31->id, 1, true)->get();
        self::assertCount(2, $collection);

        $collection = MultiCategory::parentsByModelId($node231->id)->get();
        self::assertCount(2, $collection);
    }
}
