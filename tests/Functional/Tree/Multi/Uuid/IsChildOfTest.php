<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi\Uuid;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithUuid;
use PHPUnit\Framework\Attributes\Test;

/**
 * `isDirectChildOf()` compares the parent column with the key strictly, so it has to hold up
 * where both are strings rather than integers.
 */
class IsChildOfTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategoryWithUuid>
     */
    protected static function modelClass(): string
    {
        return MultiCategoryWithUuid::class;
    }

    #[Test]
    public function isDirectChildOfWorksOnStringKeys(): void
    {
        /** @var MultiCategoryWithUuid $root */
        $root = static::model(['title' => 'root']);
        $root->save();

        /** @var MultiCategoryWithUuid $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root->refresh())->save();

        /** @var MultiCategoryWithUuid $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($child->refresh())->save();

        $child = $child->refresh();
        $leaf  = $leaf->refresh();

        static::assertIsString($leaf->parentValue());
        static::assertIsString($child->getKey());

        static::assertTrue($leaf->isDirectChildOf($child));
        static::assertFalse($leaf->isDirectChildOf($root->refresh()));
        static::assertTrue($leaf->isDescendantOf($root));
    }
}
