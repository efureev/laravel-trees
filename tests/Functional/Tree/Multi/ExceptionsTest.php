<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Exceptions\TreeNeedValueException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\MultiCategory;
use Fureev\Trees\Tests\models\v5\MultiCategoryWithoutTreeGenerator;
use PHPUnit\Framework\Attributes\Test;

/**
 * Behavioural coverage for the package exceptions on multi-trees (category C).
 */
class ExceptionsTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<MultiCategory>
     */
    protected static function modelClass(): string
    {
        return MultiCategory::class;
    }

    #[Test]
    public function deletingMultiRootWithChildrenThrows(): void
    {
        /** @var MultiCategory $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        static::model(['title' => 'child 2.1'])->prependTo($root)->save();

        $root->refresh();

        $this->expectException(DeletedNodeHasChildrenException::class);
        $this->expectExceptionMessage('Deleted Node has children.');

        $root->delete();
    }

    #[Test]
    public function savingRootWithoutTreeGeneratorThrows(): void
    {
        $model = new MultiCategoryWithoutTreeGenerator(['title' => 'root node']);

        $this->expectException(TreeNeedValueException::class);
        $this->expectExceptionMessage('Model must contained {tree_id} ID');

        $model->makeRoot()->save();
    }
}
