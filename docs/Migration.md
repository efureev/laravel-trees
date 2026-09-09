# Database Migration

The tree lives on your own table — five columns and three indexes, added by the package.

## Adding the columns

```php
<?php

use Fureev\Trees\Database\Migrate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', static function (Blueprint $table) {
            $table->integerIncrements('id');
            $table->string('title');

            Migrate::columnsFromModel($table, \App\Models\Category::class);

            $table->timestamps();
        });
    }
};
```

`columnsFromModel()` reads the model's own configuration, so custom column names and key types
are picked up without repeating them here. It is the form to prefer.

Without a model — when the table is created before the model exists:

```php
use Fureev\Trees\Config\Builder;

(new Migrate(Builder::default(), $table))->buildColumns();        // single tree
(new Migrate(Builder::defaultMulti(), $table))->buildColumns();   // multi-tree
```

## What gets added

| Column | Default name | Type |
|---|---|---|
| left bound | `lft` | unsigned integer |
| right bound | `rgt` | unsigned integer |
| level | `lvl` | unsigned integer |
| parent | `parent_id` | matches the model's key type, nullable |
| tree | `tree_id` | multi-tree only; integer, UUID or ULID |

And three indexes — `rgt`, `parent_id`, and `lft, rgt` together. On a multi-tree table the tree
column is prepended to each, since every query is scoped by tree first. See
[Architecture](./Architecture.md#schema-and-indexes).

## Soft deletes

Add Laravel's column alongside:

```php
Migrate::columnsFromModel($table, Category::class);
$table->softDeletes();
```

## Rolling back

Add a `down()` to the same migration class. `dropColumns()` removes the tree columns together
with the indexes `buildColumns()` created:

```php
public function down(): void
{
    Schema::table('categories', static function (Blueprint $table) {
        (new \Fureev\Trees\Database\Migrate(Builder::default(), $table))->dropColumns();
    });
}
```

> [!WARNING]
> Pass the same builder the migration was created with — `Builder::default()` for a single tree,
> `Builder::defaultMulti()` for a multi-tree one. The column and index names come from it, and a
> mismatch means the drop looks for names that are not there.

Dropping the whole table needs none of this.

## Adding a tree to an existing table

The columns can be added to a populated table, but the rows then have no bounds. Fill them in
after the migration:

```php
Category::fixTree();   // rebuild the bounds from parent_id
```

That requires `parent_id` to already hold the structure. See
[Health Checks and Fixing](./HealthAndFix.md), and note the caveat about the repair trait there.

## Related

- [Quick Start](./QuickStart.md) — the migration in context
- [Advanced Tree Configuration](./AdvancedTreeConfig.md) — renaming the columns, UUID and ULID keys
