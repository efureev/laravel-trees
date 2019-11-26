<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Config;
use Fureev\Trees\Migrate;
use Fureev\Trees\Tests\AbstractTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class MigrateTest extends AbstractTestCase
{
    private static $tableName = 'test_config';
    private static $singleColumns = ['lft', 'rgt', 'parent_id', 'lvl'];
    private static $treeColumn = 'tree_id';


    public function testSingleGetColumns(): void
    {
        $table = new Blueprint(self::$tableName);

        Migrate::getColumns($table, new Config());

        static::assertCount(count(self::$singleColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], self::$singleColumns);
        }
        // @todo verify indexes
//        $table->getCommands();
    }

    public function testMultiGetColumns(): void
    {
        $table = new Blueprint(self::$tableName);

        Migrate::getColumns($table, new Config([
            'treeAttribute' => self::$treeColumn,
        ]));

        $multiColumns = array_merge(self::$singleColumns, [self::$treeColumn]);

        static::assertCount(count($multiColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], $multiColumns);
        }
        // @todo verify indexes
//        dd($table->getCommands());
    }

}
