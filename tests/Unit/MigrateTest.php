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

    private static $singleColumns = ['lft', 'rgt', 'lvl', 'parent_id'];


    public function testSingleGetColumns(): void
    {
        $table = new Blueprint(self::$tableName);

        Migrate::columns($table, new Config\Base());

        static::assertCount(count(self::$singleColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], self::$singleColumns);
        }
    }

    public function testMultiGetColumns(): void
    {
        $table = new Blueprint(self::$tableName);

        Migrate::columns($table, new Config\Base(true));

        $multiColumns = array_merge(self::$singleColumns, ['tree_id']);

        static::assertCount(count($multiColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], $multiColumns);
            if ($column->getAttributes()['name'] === 'tree_id') {
                static::assertEquals('integer', $column->getAttributes()['type']);
                static::assertFalse($column->getAttributes()['nullable']);
                static::assertTrue($column->getAttributes()['unsigned']);
                static::assertNull($column->getAttributes()['default']);
            }
        }
    }

    public function testMultiCustomGetColumns(): void
    {
        $table = new Blueprint(self::$tableName);
        Migrate::columns(
            $table,
            new Config\Base(
                (new Config\TreeAttribute('tid'))->setType('uuid')->setNullable()
            )
        );

        $multiColumns = array_merge(self::$singleColumns, ['tid']);

        static::assertCount(count($multiColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], $multiColumns);
            if ($column->getAttributes()['name'] === 'tid') {
                static::assertEquals('uuid', $column->getAttributes()['type']);
                static::assertTrue($column->getAttributes()['nullable']);
                static::assertNull($column->getAttributes()['default']);
            }
        }
    }

}
