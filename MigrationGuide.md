# Migration Guide

## From v6.1 to v6.2

No breaking API changes. Three behaviours changed in ways worth knowing about, all of them
fixes to something that was silently wrong.

### `makeRoot()` on an existing node

Promoting an existing node to a root used to be a silent no-op unless `forceSave()` was used,
because `makeRoot()` changes no attribute and the save was skipped. It now works through a plain
`save()`, clears the node's `parent_id`, and takes the subtree along:

```php
$node->makeRoot()->save();
```

On a **single-tree** model the same call now raises
`Can not move a node as the root when Model is not set to "MultiTree"`. The exception was always
there; the skipped save just meant it never ran. If your code called `makeRoot()->save()` on a
single-tree node and relied on nothing happening, it will now fail loudly.

### Existence queries over the tree relations

`has()`, `whereHas()`, `doesntHave()` and `withCount()` over `ancestors` and `descendants` used
to raise `BadMethodCallException`. They work now, and are scoped per tree on multi-tree models.

### `Migrate::dropColumns()`

Rolling a migration back used to fail with `index "..." does not exist`, because the index names
were built differently on creation and removal. Migrations whose `down()` never worked will now
run.

### Health check counts

`DuplicatesCheck::check()` and `WrongParentCheck::check()` return a number of offending nodes
now. They used to return a number of ordered pairs and of (child, parent, intermediate) triples,
so the figure grew with the size of the tree for one and the same defect. Zero still means a
healthy tree, so `isBroken()` and `getTotalErrors() > 0` behave exactly as before; only code
comparing against a specific non-zero count is affected.

`WrongParentCheck` also reports more than it did: a level that does not sit one below the parent
now counts, and a broken link is found in a tree of two nodes, which previously had no third row
for the check to notice.

### More health checks

`HealthyChecker` now runs six checks instead of three, so `check()` returns six entries.
`MissingParentCheck` was already written and merely commented out of the list; `RangeCheck` and
`RootCheck` are new. Trees that passed before may now report errors — that is the point, since
a vacated span, a second root and an orphan all used to read as healthy.

Pass the model rather than its class name where the connection or the query scope matters:
`new HealthyChecker($node)`.

### Repairing a tree places orphans differently

`fixTree()` used to make a node with a missing parent into a root. It now attaches it to the
root of the tree being repaired, because a single tree may hold only one root. If you relied on
the old behaviour to split a tree, promote the node yourself with `makeRoot()` after the repair.

### Nothing to do

No configuration changes, no schema changes, no code changes required.

## From v5 to v6

`v6` only raises the minimum platform requirements — there are **no breaking API changes**.

### Requirements

- `PHP >= 8.4` (previously `8.2`)
- `Laravel >= 13` (`illuminate/*: ^13.0`; support for Laravel 11/12 was dropped)

### Steps

1. Make sure your application runs on PHP 8.4+ and Laravel 13+.
2. Update the dependency:

   ```shell
   composer require efureev/laravel-trees:^6.0
   ```

3. No code changes are required: the public API (`UseTree`, `Builder`, `Migrate`, query builder methods, etc.)
   is unchanged.

## From v4 to v5

### Migrations

old

```php
Migrate::columns($table, (new Category())->getTreeConfig());
```

new

```php
Migrate::columnsFromModel($table, Category::class);
```

### Models

old

```php
class Category extends Model
{
    use NestedSetTrait;
    
    protected static function buildTreeConfig(): Base
    {
        return new Base(TreeAttribute::make()->setUuidType()->setName('group_id')->setAutoGenerate(false));
    }
}
```

new

```php
class Category extends Model
{
    use UseTree;
    
     protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()
            ->setAttribute(
                Attribute::make(AttributeType::Tree, FieldType::UUID)
                    ->setColumnName('group_id')
            );
    }
}
```
