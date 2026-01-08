<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\QueryBuilder;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryCustomKey;
use PHPUnit\Framework\Attributes\Test;

class ParentsByModelIdCustomKeyTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategoryCustomKey>
     */
    protected static function modelClass(): string
    {
        return MultiCategoryCustomKey::class;
    }

    #[Test]
    public function basic(): void
    {
        /** @var MultiCategoryCustomKey $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->save();

        static::assertNotNull($modelRoot->tree_id);

        // Level 2
        /** @var MultiCategoryCustomKey $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($modelRoot)->save();

        // Level 3
        /** @var MultiCategoryCustomKey $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        $collection = MultiCategoryCustomKey::parentsByModelId($node31->getKey())->get();

        self::assertCount(2, $collection);
    }
}
