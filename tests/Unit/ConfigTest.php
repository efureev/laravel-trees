<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Config;

class ConfigTest extends AbstractUnitTestCase
{
    private static $singleColumns = ['lft', 'rgt', 'parent_id', 'lvl'];
    private static $treeColumn = 'tree_id';


    public function testSingleColumns(): void
    {
        $config = new Config();

        static::assertCount(count(self::$singleColumns), $config->getColumns());
        static::assertFalse($config->isMultiTree());
    }

    public function testMultiColumns(): void
    {
        $config = new Config(['treeAttribute' => 'tree']);

        $multiColumns = array_merge(self::$singleColumns, [self::$treeColumn]);
        static::assertCount(count($multiColumns), $config->getColumns());
        static::assertTrue($config->isMultiTree());
    }

    public function testMultiCustomColumns(): void
    {
        $customCols = [
            'leftAttribute' => '_lft',
            'rightAttribute' => '_rgt',
            'treeAttribute' => '_tree',
            'levelAttribute' => '_lvl',
            'parentAttribute' => 'pid',
        ];

        $config = new Config($customCols);


        static::assertCount(count($customCols), $config->getColumns());
        static::assertTrue($config->isMultiTree());

        foreach ($customCols as $colName => $colVal) {
            $method = 'get' . ucfirst($colName) . 'Name';
            static::assertEquals($colVal, $config->$method());
        }
    }
}
