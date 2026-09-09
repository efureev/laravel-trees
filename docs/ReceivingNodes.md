# Retrieving Nodes

Everything here is a read. Costs are given per call — see [Performance](./Performance.md) for
where the numbers come from.

## Choosing the right tool

| You want | Use |
|---|---|
| the direct parent | `parent` |
| the chain up to the root | `ancestors` or `parents()` |
| the direct children | `children` |
| the whole subtree | `descendants` |
| neighbours on the same level | `siblings()`, `prevSibling()`, `nextSibling()` |
| the tree as a structure | `->get()->toTree()`, see [Collections](./Collections.md) |
| to filter by what a node has | `has()`, `whereHas()`, `withCount()` |

## Upwards

```php
$node->parent;                    // the direct parent, or null for a root
$node->parents();                 // Collection of ancestors, root first
$node->ancestors;                 // the same as a relation
$node->parentByLevel(1);          // the single ancestor sitting at level 1
```

`parents()` and `ancestors` are ordered from the root down. `parentByLevel()` returns **one
model**, not a chain — it is `parents($level)->first()`.

Each of these is one query.

## Downwards

```php
$node->children;                  // direct children, ordered
$node->descendants;               // the whole subtree, any depth
$node->descendants()->count();    // without loading it
```

One query each, whatever the depth. `descendants` comes back in no defined order — see
[Limitations](./Limitations.md#names-that-mean-something-slightly-different).

Limiting the depth happens in the query, not in PHP:

```php
$node->newNestedSetQuery()->descendantsQuery(1)->get();   // children only
```

## Sideways

```php
$node->siblings()->get();         // same parent, this node excluded
$node->siblingsAndSelf()->get();

$node->prevSibling()->first();    // the sibling immediately before
$node->nextSibling()->first();
$node->prevSiblings()->get();     // all siblings before
$node->nextSiblings()->get();
```

Ignoring the parent and walking the tree by position instead:

```php
$node->prev()->first();           // the node immediately before, anywhere
$node->next()->first();
$node->prevNodes()->get();        // everything before it
$node->nextNodes()->get();        // everything after it
```

> [!NOTE]
> `nextNodes()` includes the node's **own descendants** — they open after it in the numbering.

## Leaves

```php
$node->newNestedSetQuery()->leaves()->get();    // leaves of this subtree
$node->newNestedSetQuery()->leaves(1)->get();   // only one level down
Category::query()->leaf()->get();               // every leaf in the table
```

`leaf()` narrows a query to nodes with no children; `leaves()` is that applied to a subtree.

## Trashed nodes

On a model using `SoftDeletes`, trashed nodes are hidden. Two shortcuts, and the usual escape
hatch:

```php
$node->childrenWithTrashed;
$node->parentWithTrashed;
$node->descendants()->withTrashed()->get();
```

See [Soft Deletes](./SoftDeletes.md).

## Scoping a query

Static on the model, or on any builder:

```php
Category::root()->first();          // the root, or roots of a multi-tree
Category::notRoot()->get();         // everything else

Category::byTree($treeId)->get();   // one tree — multi-tree only
Category::byLevel(1)->get();        // exactly level 1
Category::toLevel(1)->get();        // level 1 and above
Category::byParent($node)->get();   // the direct children of a node
```

Ancestors of a node you have only the id of:

```php
MultiCategory::parentsByModelId($id)->get();
MultiCategory::parentsByModelId($id, level: 1)->get();
MultiCategory::parentsByModelId($id, andSelf: true)->get();
```

> [!WARNING]
> `parentsByModelId()` works on multi-tree models only. On a single tree it raises
> `NotSupportedException`, which — like every error the package throws — extends
> `Fureev\Trees\Exceptions\Exception`.

## Ordering

```php
Category::query()->defaultOrder()->get();              // by lft, ascending
Category::query()->defaultOrder(SORT_DESC)->get();
```

> [!NOTE]
> `defaultOrder()` **replaces** any ordering already on the query rather than adding to it.

## Filtering by a relation

`ancestors` and `descendants` are ordinary Eloquent relations, so the existence helpers work on
them. On a multi-tree model each one stays inside the node's own tree.

```php
// Nodes that have a subtree of their own, and the leaves
Category::query()->has('descendants')->get();
Category::query()->doesntHave('descendants')->get();

// Everything except the roots
Category::query()->has('ancestors')->get();

// Ancestors of the nodes matching a condition
Category::query()
    ->whereHas('descendants', static fn($query) => $query->where('title', 'Shoes'))
    ->get();

// $node->descendants_count without loading the subtree
Category::query()->withCount('descendants')->get();
```

## Related

- [Collections](./Collections.md) — turning a flat result into a tree
- [Performance](./Performance.md) — what each of these costs
- [API Reference](./ApiReference.md) — the complete list
