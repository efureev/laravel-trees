<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\CustomColumnsCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Renaming the columns must not shuffle the positional bounds array. This is where a mismatch
 * between the configured order and the order the row comes back in would show first.
 */
class NodeBoundsCustomColumnsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<CustomColumnsCategory>
     */
    protected static function modelClass(): string
    {
        return CustomColumnsCategory::class;
    }

    #[Test]
    public function customColumnNamesKeepTheOrder(): void
    {
        /** @var CustomColumnsCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var CustomColumnsCategory $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root->refresh())->save();

        $child = $child->refresh();

        static::assertSame(
            [
                'left_bound',
                'right_bound',
                'depth',
                'pid',
            ],
            $child->getTreeConfig()->columnsNames()
        );

        $expected = [
            $child->leftValue(),
            $child->rightValue(),
            $child->levelValue(),
            $child->parentValue(),
        ];

        static::assertSame($expected, $child->getNodeBounds($child));
        static::assertSame($expected, $child->getNodeBounds($child->getKey()));
    }
}
