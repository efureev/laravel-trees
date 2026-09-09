<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\TreeNeedValueException;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\NoGeneratorCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * A multi-tree model may decline to generate tree ids. Then a root without one is refused
 * rather than silently given a value.
 */
class NoGeneratorTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<NoGeneratorCategory>
     */
    protected static function modelClass(): string
    {
        return NoGeneratorCategory::class;
    }

    #[Test]
    public function aRootWithoutATreeIdIsRefused(): void
    {
        $this->expectException(TreeNeedValueException::class);

        static::model(['title' => 'root'])->makeRoot()->save();
    }

    #[Test]
    public function aRootWithAnExplicitTreeIdIsAccepted(): void
    {
        /** @var NoGeneratorCategory $root */
        $root = static::model(['title' => 'root']);
        $root->setTree(7)->makeRoot()->save();

        static::assertSame(7, $root->refresh()->treeValue());
        static::assertTrue($root->isRoot());
    }
    /**
     * Promoting an existing node to a root needs a tree id of its own, so the same refusal
     * happens on that path too — a different call site from the one a new root goes through.
     */
    #[Test]
    public function promotingANodeWithoutAGeneratorIsRefused(): void
    {
        /** @var NoGeneratorCategory $root */
        $root = static::model(['title' => 'root']);
        $root->setTree(7)->makeRoot()->save();

        /** @var NoGeneratorCategory $child */
        $child = static::model(['title' => 'child']);
        $child->appendTo($root->refresh())->save();

        $this->expectException(TreeNeedValueException::class);

        $child->refresh()->makeRoot()->save();
    }
}
