# Quick Start

> **From nothing to a working tree in one pass.** Every number printed in the comments below is
> asserted by a test, so what you see here is what you get.

## 1. Install

```shell
composer require efureev/laravel-trees
```

There is nothing to register — no service provider, no config file to publish. Adding the trait
to a model is the whole setup.

## 2. Add the columns

The tree lives on your own table. Let the package add the columns and their indexes:

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
        });
    }
};
```

That adds `lft`, `rgt`, `lvl` and `parent_id`, plus the three indexes the package queries
through. See [Database Migration](./Migration.md) for multi-tree tables, custom names and the
rollback.

## 3. Add the trait

```php
<?php

namespace App\Models;

use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use UseTree;
}
```

That is a **single tree**: one root, no tree column. For several independent trees in one table,
see [Basic Usage](./Basic.md).

## 4. Create the root

A single tree has no way to guess where the first node belongs, so say it outright:

```php
$root = Category::make(['title' => 'Catalogue']);
$root->makeRoot()->save();

$root->isRoot();       // true
$root->leftValue();    // 1
$root->rightValue();   // 2
$root->levelValue();   // 0
```

> [!WARNING]
> `Category::create([...])` on a single-tree model raises `NotSupportedException`. Every node
> needs a position, and the first one has to be told it is the root.

## 5. Add nodes

Position the node, then save it. Positioning on its own writes nothing — see
[Architecture](./Architecture.md#operations-are-deferred).

```php
$shoes = Category::make(['title' => 'Shoes']);
$shoes->appendTo($root)->save();

$boots = Category::make(['title' => 'Boots']);
$boots->appendTo($shoes->refresh())->save();

$bags = Category::make(['title' => 'Bags']);
$bags->appendTo($root->refresh())->save();
```

```
Catalogue
├── Shoes
│   └── Boots
└── Bags
```

`appendTo()` puts the node last among its parent's children; `prependTo()` puts it first, and
`insertBefore()` / `insertAfter()` place it next to a sibling.

## 6. Read it back

```php
$root->descendants()->count();   // 3  — the whole subtree, one query, any depth
$root->children()->count();      // 2  — direct children only

$boots->ancestors()->count();    // 2
$boots->ancestors()->get()->pluck('title')->all();   // ['Catalogue', 'Shoes'] — root first
```

The entire tree in one query, linked up in memory:

```php
$tree = Category::query()->defaultOrder()->get()->toTree();

$tree->count();          // 1  — roots
$tree->totalCount();     // 4  — nodes that went in
$tree->first()->children->pluck('title')->all();   // ['Shoes', 'Bags']
```

See [Retrieving Nodes](./ReceivingNodes.md) and [Collections](./Collections.md) for the rest.

## 7. Move a subtree

Moving a node takes its descendants with it:

```php
$shoes->appendTo($bags->refresh())->save();

$boots->refresh()->isChildOf($bags);   // true — Boots came along
```

## 8. Delete

Deleting a node lifts its children one level by default:

```php
$shoes->delete();

$boots->refresh()->parentValue();   // Bags — Boots was pulled up
Category::query()->count();         // 3
```

> [!WARNING]
> Delete through the model, never through a query. `Category::query()->whereKey($id)->delete()`
> removes the row without closing the gap and breaks the tree for good — see
> [Limitations](./Limitations.md#never-delete-through-a-query).

To remove a node together with its subtree, use `deleteWithChildren()`.

## Where to go next

| If you want | Read |
|---|---|
| Several trees in one table | [Basic Usage](./Basic.md) |
| Custom column names, UUID or ULID keys | [Advanced Tree Configuration](./AdvancedTreeConfig.md) |
| To understand why `appendTo()` needs `save()` | [Architecture](./Architecture.md) |
| To know what this costs and what it forbids | [Limitations](./Limitations.md) |
