<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Config\Base;
use Fureev\Trees\Tests\AbstractTestCase;

class BaseConfigTest extends AbstractTestCase
{
    private static $singleColumns = ['lft', 'rgt', 'lvl', 'parent_id'];
    private static $treeColumn = 'tree_id';


    public function testSingleTreeColumns(): void
    {
        $config = new Base();

        static::assertFalse($config->isMultiTree());
        static::assertCount(count(self::$singleColumns), $config->columns());
    }

    public function testMultiTreeColumns(): void
    {
        $multiColumns = array_merge(self::$singleColumns, [self::$treeColumn]);

        $config = new Base(true);
        static::assertTrue($config->isMultiTree());
        static::assertEquals($multiColumns, $config->columns());

        $config = (new Base())->setMultiTree();
        static::assertTrue($config->isMultiTree());
        static::assertEquals($multiColumns, $config->columns());
    }

    public function testMultiCustomColumns(): void
    {
        $config = new Base(true);

        $config->level()->setName('_level');
        $config->left()->setName('_left');
        $config->right()->setName('_right');
        $config->parent()->setName('_pid')->setType('uuid');
        $config->tree()->setName('_tree')->setType('uuid')->nullable();

        static::assertTrue($config->isMultiTree());

        static::assertEquals('_left', (string)$config->left());
        static::assertEquals('_left', $config->left()->name());
        static::assertEquals('unsignedInteger', $config->left()->type());

        static::assertEquals('_right', (string)$config->right());
        static::assertEquals('_right', $config->right()->name());
        static::assertEquals('unsignedInteger', $config->right()->type());

        static::assertEquals('_level', (string)$config->level());
        static::assertEquals('_level', $config->level()->name());
        static::assertEquals('integer', $config->level()->type());

        static::assertEquals('_pid', (string)$config->parent());
        static::assertEquals('_pid', $config->parent()->name());
        static::assertEquals('uuid', $config->parent()->type());

        static::assertEquals('_tree', (string)$config->tree());
        static::assertEquals('_tree', $config->tree()->name());
        static::assertEquals('uuid', $config->tree()->type());

        $config = new Base();
        static::assertNull($config->tree());
    }
}
