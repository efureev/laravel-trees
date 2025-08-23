<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Database;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Database\Migrate;
use Fureev\Trees\Tests\AbstractTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

class MigrateTest extends AbstractTestCase
{
    private static string $tableName = 'test_config';

    protected static function isLaravel12(): bool
    {
        return str_starts_with(Application::VERSION, '12');
    }

    protected function getBlueprint(string $table): Blueprint
    {
        return self::isLaravel12()
            ? new Blueprint($this->getConnection(), $table)
            : new Blueprint($table);
    }

    #[Test]
    public function columnsForUnoTree(): void
    {
        $table = $this->getBlueprint(self::$tableName);
        $builder = Builder::default();

        (new Migrate($builder, $table))->buildColumns();

        $expectedColumns = $builder->columnsNames();

        static::assertCount(count($expectedColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], $expectedColumns);
        }
    }

    #[Test]
    public function buildColumnsExcludesTreeColumn(): void
    {
        $table = $this->getBlueprint(self::$tableName);
        $builder = Builder::defaultMulti();

        (new Migrate($builder, $table))->buildColumns(true);

        foreach ($table->getColumns() as $column) {
            static::assertNotEquals($builder->tree()->columnName(), $column->getAttributes()['name']);
        }
    }

    #[Test]
    public function columnsForMultiTree(): void
    {
        $table = $this->getBlueprint(self::$tableName);
        $builder = Builder::defaultMulti();

        (new Migrate($builder, $table))->buildColumns();

        $expectedColumns = $builder->columnsNames();

        static::assertCount(count($expectedColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], $expectedColumns);

            if ($column->getAttributes()['name'] === $builder->tree()->columnName()) {
                static::assertEquals('integer', $column->getAttributes()['type']);
                static::assertFalse($column->getAttributes()['nullable']);
                static::assertTrue($column->getAttributes()['unsigned']);
                static::assertNull($column->getAttributes()['default']);
            }

            if ($column->getAttributes()['name'] === $builder->parent()->columnName()) {
                static::assertEquals('integer', $column->getAttributes()['type']);
                static::assertTrue($column->getAttributes()['nullable']);
                static::assertTrue($column->getAttributes()['unsigned']);
                static::assertNull($column->getAttributes()['default']);
            }
        }
    }

    #[Test]
    public function columnsForUuidMultiTree(): void
    {
        $table = $this->getBlueprint(self::$tableName);
        $builder = Builder::defaultMulti();
        $builder->tree()->setType(FieldType::UUID)->setColumnName('tid');

        (new Migrate($builder, $table))->buildColumns();

        $expectedColumns = $builder->columnsNames();

        static::assertCount(count($expectedColumns), $table->getColumns());

        foreach ($table->getColumns() as $column) {
            /** @var ColumnDefinition $col */
            static::assertContains($column->getAttributes()['name'], $expectedColumns);

            if ($column->getAttributes()['name'] === $builder->tree()->columnName()) {
                static::assertEquals('tid', $column->getAttributes()['name']);
                static::assertEquals('uuid', $column->getAttributes()['type']);
                static::assertFalse($column->getAttributes()['nullable']);
                static::assertNull($column->getAttributes()['default']);
            }

            if ($column->getAttributes()['name'] === $builder->parent()->columnName()) {
                static::assertEquals('integer', $column->getAttributes()['type']);
                static::assertTrue($column->getAttributes()['nullable']);
                static::assertTrue($column->getAttributes()['unsigned']);
                static::assertNull($column->getAttributes()['default']);
            }
        }
    }
}
