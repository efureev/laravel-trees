# Use Cases

Six scenarios, end to end. Each one ends with what it costs, in statements.

| Scenario | Shape | Key type |
|---|---|---|
| [Product catalogue](#product-catalogue) | single tree | integer |
| [One tree per tenant](#one-tree-per-tenant) | multi-tree | integer |
| [Org chart across legal entities](#org-chart-across-legal-entities) | multi-tree | UUID |
| [Navigation menu](#navigation-menu) | either | any |
| [Search results with context](#search-results-with-context) | either | any |
| [Archive](#archive) | either | any |

---

## Product catalogue

One tree, integer keys — the default shape.

```php
class Category extends Model
{
    use UseTree;
}
```

```php
$root = Category::make(['title' => 'Catalogue']);
$root->makeRoot()->save();

$shoes = Category::make(['title' => 'Shoes']);
$shoes->appendTo($root)->save();
```

Reading a section with everything under it, at any depth:

```php
$shoes->descendants()->get();
```

Moving a whole section elsewhere — the subtree follows:

```php
$shoes->appendTo($clothing)->save();
```

**What it costs.** Reading a section: 1 statement. Adding a category: 3. Moving a section: 5,
plus a bound shift across the rows after it.

---

## One tree per tenant

Every tenant gets an independent tree in the same table. Bounds repeat across tenants — each
tree numbers from 1 — and the tree column is what keeps them apart.

```php
class Category extends Model
{
    use UseTree;

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti();
    }
}
```

A root created for a tenant starts a new tree. Either let the id be generated, or pin it to the
tenant's own id:

```php
Category::make(['title' => 'Catalogue'])->setTree($tenantId)->makeRoot()->save();
```

Everything below that root inherits the tree, and every query the package builds is scoped by it
automatically. To scope a query of your own:

```php
Category::query()->byTree($tenantId)->get();
```

> [!WARNING]
> Bounds alone do not separate tenants — two tenants' trees hold the same numbers. Never build a
> raw bound query without the tree column.

**What it costs.** The same as a single tree; the tree column is an equality check on the
leading column of every index.

---

## Org chart across legal entities

UUID keys, one tree per legal entity, people and units moving between them.

```php
class Unit extends Model
{
    use UseTree;
    use HasUuids;

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()
            ->setAttribute(Attribute::make(AttributeType::Tree, FieldType::UUID));
    }
}
```

Moving a department to another entity takes its people with it, and the tree column travels
along with the subtree:

```php
$department->appendTo($otherEntityRoot)->save();
```

Spinning a department off into an entity of its own:

```php
$department->makeRoot()->save();
```

The department becomes a root, its subtree comes along, and the gap it left is closed. See
[Managing Nodes](./ManagingNodes.md#promote-a-node-to-a-root).

**What it costs.** Either move is 5 statements. The generated tree id is a UUID v7, so it sorts
by creation time.

---

## Navigation menu

A menu needs two levels, not the whole tree. Cut the depth in the query rather than in PHP:

```php
$menu = Category::query()
    ->byTree($tenantId)
    ->toLevel(1)          // root and its children
    ->defaultOrder()
    ->get()
    ->toTree();
```

`toTree()` links the nodes in memory and issues no query of its own, so the menu is **one**
statement in total.

```php
foreach ($menu as $root) {
    foreach ($root->children as $child) {
        // ...
    }
}
```

> [!WARNING]
> Reading `$node->children` on a collection you did not pass through `toTree()` costs a query
> per node. That is the N+1 of this package.

**What it costs.** 1 statement, whatever the menu's width.

---

## Search results with context

A search returns scattered deep nodes. On their own they mean nothing — the user needs the path
above each one.

```php
$found = Category::query()->where('title', 'like', "%$term%")->get();

$withContext = $found->toBreadcrumbs();
```

`toBreadcrumbs()` pulls in the ancestors missing from the result and links everything up, so the
chain from the root is walkable:

```php
$root = $withContext->first();
$root->children->first()->title;   // the next step down
```

**What it costs.** 1 statement for the search, **1** for all the missing ancestors together —
however many results are missing them.

---

## Archive

Nodes are hidden rather than removed, and can come back.

```php
class Category extends Model
{
    use UseTree;
    use SoftDeletes;
}
```

```php
$node->delete();                 // hidden, still holds its place in the tree
$node->restore();                // back
```

The node keeps its bounds while trashed, which is what makes the restore exact. Restoring
something deep usually means restoring the path to it as well, or nothing can navigate there:

```php
Category::withTrashed()->find($id)->restoreWithParents();
```

Reading around the archive:

```php
$node->descendants()->count();                 // living only
$node->descendants()->withTrashed()->count();  // including archived
```

**What it costs.** A soft delete is a single `UPDATE` and renumbers nothing — cheaper than a
hard delete. See [Soft Deletes](./SoftDeletes.md).

---

## Related

- [Quick Start](./QuickStart.md) — the shortest path to a working tree
- [Performance](./Performance.md) — where the statements above come from
- [Limitations](./Limitations.md) — what none of these scenarios can do
