# Tree-structured Models Migration Guide

## Overview
When implementing Tree-structured Models in your application, you'll need to modify your database schema. This guide explains how to perform this migration safely.

## Migration Steps

1. Back up your existing database
2. Use the Migration Helper utility to generate the necessary schema changes
3. Review the generated migration
4. Apply the migration to your database

## Using Migration Helper

```php
<?php
return new class extends Migration {
    protected static $tableName = 'categories';

    public function up()
    {
        Schema::create(
            static::$tableName,
            static function (Blueprint $table) {
                $table->uuid()->primary();
                // ...
                // priority manner:
                \Fureev\Trees\Database\Migrate::columnsFromModel($table, YourModel::class);
                
                // or custom for single tree:
                // (new \Fureev\Trees\Database\Migrate(Builder::default(), $table))->buildColumns();
                
                // or custom for multi-tree:
                // (new \Fureev\Trees\Database\Migrate(Builder::defaultMulti(), $table))->buildColumns();
                
                $table->timestamps();
                $table->softDeletes(); // if you need softDelete
            }
        );
    }
};
```

## Rolling the migration back

Add a `down()` to the same migration class. `Migrate::dropColumns()` removes the tree columns
together with the indexes `buildColumns()` created:

```php
public function down()
{
    Schema::table(
        static::$tableName,
        static function (Blueprint $table) {
            (new \Fureev\Trees\Database\Migrate(Builder::default(), $table))->dropColumns();
        }
    );
}
```

Pass the same builder the migration was created with — `Builder::default()` for a single tree,
`Builder::defaultMulti()` for a multi-tree one — otherwise the column and index names will not
match what is in the database.
