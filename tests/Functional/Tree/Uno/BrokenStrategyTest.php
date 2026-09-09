<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\BrokenStrategyCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * The builder takes a class name, so a wrong one is only found when the strategy is resolved.
 * Both resolvers check, and neither check had ever run.
 */
class BrokenStrategyTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<BrokenStrategyCategory>
     */
    protected static function modelClass(): string
    {
        return BrokenStrategyCategory::class;
    }

    /**
     * @return array<string, BrokenStrategyCategory>
     */
    private function buildTree(): array
    {
        /** @var BrokenStrategyCategory $root */
        $root = static::model(['title' => 'root']);
        $root->makeRoot()->save();

        /** @var BrokenStrategyCategory $branch */
        $branch = static::model(['title' => 'branch']);
        $branch->appendTo($root->refresh())->save();

        /** @var BrokenStrategyCategory $leaf */
        $leaf = static::model(['title' => 'leaf']);
        $leaf->appendTo($branch->refresh())->save();

        return [
            'root'   => $root->refresh(),
            'branch' => $branch->refresh(),
            'leaf'   => $leaf->refresh(),
        ];
    }

    #[Test]
    public function aDeleterThatIsNotAStrategyIsRefused(): void
    {
        $nodes = $this->buildTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Delete Strategy for `deleteWithChildren`');

        $nodes['branch']->deleteWithChildren();
    }

    #[Test]
    public function aChildrenHandlerThatIsNotAStrategyIsRefused(): void
    {
        $nodes = $this->buildTree();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ChildrenHandler for `delete`');

        $nodes['branch']->delete();
    }
}
