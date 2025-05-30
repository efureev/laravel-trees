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
