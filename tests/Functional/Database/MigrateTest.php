<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Database;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Database\Migrate;
use Fureev\Trees\Tests\AbstractTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;

class MigrateTest extends AbstractTestCase
{
    private static string $tableName = 'test_config';

    protected static function getLaravelMajorVersion(): int
    {
        return (int)strtok(Application::VERSION, '.');
    }

    protected static function isLaravelGreaterThan12(): bool
    {
        return self::getLaravelMajorVersion() >= 12;
    }

    protected function getBlueprint(string $table): Blueprint
    {
        return self::isLaravelGreaterThan12()
            ? new Blueprint($this->getConnection(), $table)
            : new Blueprint($table);
    }

    #[Test]
    public function columnsForUnoTree(): void
    {
        $table   = $this->getBlueprint(self::$tableName);
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
        $table   = $this->getBlueprint(self::$tableName);
        $builder = Builder::defaultMulti();

        (new Migrate($builder, $table))->buildColumns(true);

        foreach ($table->getColumns() as $column) {
            static::assertNotEquals($builder->tree()->columnName(), $column->getAttributes()['name']);
        }
    }

    #[Test]
    public function columnsForMultiTree(): void
    {
        $table   = $this->getBlueprint(self::$tableName);
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
        $table   = $this->getBlueprint(self::$tableName);
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

    /**
     * @return string[]
     */
    private function indexNamesOf(Blueprint $table, string $commandName): array
    {
        $names = [];
        foreach ($table->getCommands() as $command) {
            if ($command->get('name') === $commandName) {
                $names[] = $command->get('index');
            }
        }

        sort($names);

        return $names;
    }

    #[Test]
    public function createAndDropUseTheSameIndexNames(): void
    {
        $builder = Builder::default();

        $created = $this->getBlueprint(self::$tableName);
        (new Migrate($builder, $created))->buildColumns();

        $dropped = $this->getBlueprint(self::$tableName);
        (new Migrate($builder, $dropped))->dropColumns();

        static::assertNotEmpty($this->indexNamesOf($created, 'index'));
        static::assertSame(
            $this->indexNamesOf($created, 'index'),
            $this->indexNamesOf($dropped, 'dropIndex')
        );
    }

    #[Test]
    public function dropColumnsRemovesTreeColumns(): void
    {
        $this->assertRoundTrip(Builder::default(), 'drop_uno');
    }

    #[Test]
    public function dropColumnsRemovesTreeColumnsForMultiTree(): void
    {
        $this->assertRoundTrip(Builder::defaultMulti(), 'drop_multi');
    }

    /**
     * Creates a real table with the tree columns, drops them again and asserts both halves ran.
     */
    private function assertRoundTrip(Builder $builder, string $table): void
    {
        Schema::create(
            $table,
            static function (Blueprint $blueprint) use ($builder) {
                $blueprint->integerIncrements('id');
                (new Migrate($builder, $blueprint))->buildColumns();
            }
        );

        foreach ($builder->columnsNames() as $column) {
            static::assertTrue(Schema::hasColumn($table, $column), "column [$column] was not created");
        }

        Schema::table(
            $table,
            static function (Blueprint $blueprint) use ($builder) {
                (new Migrate($builder, $blueprint))->dropColumns();
            }
        );

        foreach ($builder->columnsNames() as $column) {
            static::assertFalse(Schema::hasColumn($table, $column), "column [$column] was not dropped");
        }
    }
}
