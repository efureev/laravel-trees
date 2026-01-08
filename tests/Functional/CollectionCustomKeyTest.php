<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Collection;
use Fureev\Trees\Tests\models\v5\CategoryCustomKey;
use PHPUnit\Framework\Attributes\Test;

class CollectionCustomKeyTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CategoryCustomKey>
     */
    protected static function modelClass(): string
    {
        return CategoryCustomKey::class;
    }

    #[Test]
    public function fillMissingIntermediateNodesWithCustomKeyName(): void
    {
        /** @var CategoryCustomKey $modelRoot */
        $modelRoot = static::model(['title' => 'root node']);
        $modelRoot->makeRoot()->save();

        /** @var CategoryCustomKey $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($modelRoot)->save();

        /** @var CategoryCustomKey $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var CategoryCustomKey $node31 */
        $node31 = static::model(['title' => 'child 3.1']);
        $node31->appendTo($node21)->save();

        /** @var Collection $collection */
        $collection = new Collection([$node31]);
        $collection->fillMissingIntermediateNodes();

        static::assertCount(4, $collection);
    }
}
