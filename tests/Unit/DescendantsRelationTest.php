<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\models\Simple;

class DescendantsRelationTest extends AbstractUnitTestCase
{
    protected static $modelClass = Simple::class;

    public function testCreateAnyChildrenSingleTree(): void
    {
        $root = Simple::make(['title' => 'Root structure',])->makeRoot();
        $root->save();


        $sub1 = Simple::make(['title' => 'sub1']);
        $sub1->appendTo($root)->save();

        $sub2 = Simple::make(['title' => 'sub2']);

        $sub1->children()->save($sub2);

        static::assertEquals(0, $root->levelValue());
        static::assertEquals(1, $sub1->levelValue());
        static::assertEquals(2, $sub2->levelValue());
        static::assertNotNull($sub2->parent);
        static::assertTrue($sub1->parent->is($root));
        static::assertTrue($sub2->parent->is($sub1));
    }
}
