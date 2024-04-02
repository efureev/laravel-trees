<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Tests\Functional\Helpers\InstallMigration;
use Fureev\Trees\Tests\Functional\Helpers\StructureHelper;
use Fureev\Trees\Tests\models\SoftDeleteStructure;

/**
 * @deprecated
 */
class SoftDeleteStructureMovingTest extends AbstractFunctionalTestCase
{
    use StructureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        (new InstallMigration(SoftDeleteStructure::class))->install();
    }

    public function moveNodeBeforeDeletedNode(): void
    {
        $root        = $this->createSoftDeleteStructure(null, ['title' => 'Root']);
        $childFirst  = $this->createSoftDeleteStructure($root, ['title' => 'First']);
        $childSecond = $this->createSoftDeleteStructure($root, ['title' => 'Second']);
        $childFirst->delete();
        $root->refresh();

        static::assertEquals([1, 6, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $childFirst->treeValue()], $childFirst->getBounds());
        static::assertEquals([4, 5, 1, $root->id, $childSecond->treeValue()], $childSecond->getBounds());

        $childNewFirst = $this->createSoftDeleteStructure($root, ['title' => 'new First'], 'prependTo');
        $childSecond->refresh();
        $childFirst->refresh();
        $root->refresh();

        static::assertEquals([1, 8, 0, null, $root->treeValue()], $root->getBounds());
        static::assertEquals([2, 3, 1, $root->id, $childNewFirst->treeValue()], $childNewFirst->getBounds());
        static::assertEquals([4, 5, 1, $root->id, $childFirst->treeValue()], $childFirst->getBounds());
        static::assertEquals([6, 7, 1, $root->id, $childSecond->treeValue()], $childSecond->getBounds());
    }
}
