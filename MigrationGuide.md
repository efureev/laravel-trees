# Migration Guide

## From v6 to v7

Three signatures changed and a number of behaviours did, almost all of them because something
was silently wrong. Nothing here needs a schema change, and most applications need no code
change at all — the sections below say which ones do.

Start with **Signatures** if you extend the package's own classes, and with **Health checks** if
you compare `check()` against a literal.

### Requirements

Unchanged from v6: PHP 8.4 or newer, Laravel 13. There is nothing to upgrade before this one.

The constraint is written `^8.4` now instead of `>=8.4`, which had no upper bound and would have
let Composer install the package on PHP 9. The versions that work are the same two, and both run
on CI.

### One less dependency

`efureev/support` is gone. The tree took three small things from it — a trait whose body is
`new static(...)`, an exception class, and a global `instance()` — and a dependency for that much
means inheriting someone else's release policy: its v6 requires PHP 8.5, which nothing here needs.

One import changes if you catch it:

```php
-use Php\Support\Exceptions\InvalidConfigException;
+use Fureev\Trees\Exceptions\InvalidConfigException;
```

Same name and same message, thrown from the same place — `Migrate::columnsFromModel()` on a model
with no tree configuration. It extends the package's own `Exception` now, so a `catch` on that
catches this too.

`Attribute::make()` names its arguments rather than taking `mixed ...$arguments`. Calls written
as `Attribute::make(AttributeType::Left)` or `Attribute::make(AttributeType::Tree, FieldType::UUID)`
are unaffected; anything passing a third argument was passing something that was ignored.

### Signatures

`BaseRelation::relationExistenceCondition()` is gone, replaced by `addExistenceConstraint()`.
The old one returned a raw SQL fragment; the new one applies `whereColumn()` constraints to the
query it is handed:

```php
protected function addExistenceConstraint(Builder $query, string $hash, string $parentTable): void
```

Both are protected, and the two relations in the package are the only implementations, so this
matters only if you wrote a relation of your own on top of `BaseRelation`.

`QueryBuilder\Fixing::makeGap()` declares its parameters as `int`:

```php
public function makeGap(int $cut, int $height): int
```

Callers passing numeric strings from a file with `declare(strict_types=1)` need to cast.

`Table::getColumnNames()` is gone; `getExtraColumnNames()` does the same and says what it
returns. Both are protected on a `final` class, so nothing outside the package could reach
either.

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

### `removeDescendants()` renumbers the tree

It used to delete the rows and leave every bound where it was. It now closes the room those
rows occupied, so the node becomes a leaf and everything positioned after it moves up. Code
holding bounds read before the call has to re-read them — as it already had to after any other
write.

### The children handler runs before the delete

`childrenHandlerOnDelete` used to be called from `afterDelete()`, after the row was gone. It is
called before, so a handler that refuses by throwing now leaves the node in place. A handler
that counted on the node already being deleted has to be looked at.

### `Table::fromQuery()` builds the table

It is static now, so `(new Table())->hideLevel()->fromQuery($query)` loses the `hideLevel()`.
Move configuration after the factory — the order the other two have always required:

```php
Table::fromQuery($query)->hideLevel()->draw($output);
```

Calling it through an instance still works, so nothing fails to run; only that one order
changes meaning.

### `Contracts\TreeModel` describes the whole node

The interface declared 24 methods and now declares 66 — everything the tree traits add. Nothing
implements it and nothing tests for it (`Helper::isTreeNode()` asks whether the model uses
`UseTree`), so this breaks nothing; it means code typed as `Model&TreeModel` can call a node's
own API without static analysis objecting.

### Nothing else to do

No configuration changes and no schema changes. Everything above is either a behaviour that was
wrong before, or a signature only reachable from inside the package.

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
