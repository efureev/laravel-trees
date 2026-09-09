# Performance

> **Nested sets trade write cost for read cost.** Reads are cheap and flat; writes renumber part
> of the table. This page puts numbers on both so the trade is a decision, not a surprise.

## What each operation costs

Statement counts measured from the query log on a live tree, not estimated:

| Operation | Statements | What runs |
|---|---|---|
| Read a subtree, any depth | **1** | one `select` over a bound range |
| Read the ancestors of a node | **1** | one `select`, ordered root first |
| Read direct children | **1** | one `select` on `parent_id` |
| Insert a node | **3** | select the target, shift the bounds, insert |
| Move a node inside its tree | **5** | one select, four updates |
| Move a node to another tree | **5** | the same, and the tree column travels with the subtree |
| Delete a leaf | **3** | refresh, delete, close the gap |

The statement count is flat, but the **rows touched** by a write are not: shifting the bounds
after a change can match a large share of the table.

## Why reads are flat

A subtree is a contiguous range of `lft` values, so fetching it is a range scan and nothing
more. Depth costs nothing: a subtree ten levels deep is the same single query as one level deep.

```php
$node->descendants()->get();   // 1 query, any depth
$node->ancestors()->get();     // 1 query, root first
```

That is the whole reason to choose nested sets over an adjacency list, where the same read is
one query per level or a recursive CTE.

## Why writes are expensive

Inserting or removing a node renumbers everything positioned after it. The statement is a single
`UPDATE`, but its `WHERE` can cover half the table:

```
insert X under B      ->      every bound at or after X shifts by 2
```

The package keeps this to one statement per shift — both bound columns move together, with a
`CASE` per column — so the cost is in rows matched, not in round trips.

> [!TIP]
> If your tree is written to as often as it is read, nested sets are the wrong model. Consider
> an adjacency list with a recursive CTE, or a closure table.

## The indexes

`Migrate` creates three, matching how the package queries:

| Index on | Serves |
|---|---|
| `rgt` | closing-bound lookups, gap shifting |
| `parent_id` | direct children |
| `lft, rgt` | subtree and ancestor ranges |

On a multi-tree model the tree column is prepended to each, because every query is scoped by
tree before anything else.

## Loading a whole tree

Fetch the rows once and build the structure in memory. `toTree()` and `linkNodes()` issue **no
queries at all** — they only wire up relations on objects you already have.

```php
$tree = Category::query()->defaultOrder()->get()->toTree();   // 1 query in total
```

Compare with walking the tree through relations, which costs a query per node.

> [!WARNING]
> Reading `$node->children` in a loop over an unlinked collection is the classic N+1 here.
> Call `toTree()` once and walk `children` from there.

Breadcrumbs work the same way: `toBreadcrumbs()` fetches the ancestors that are missing from the
collection in **one** query, however many nodes are missing them.

## Keeping large trees fast

- **Limit the depth you read.** `descendantsQuery($level)` and `toLevel($level)` cut the range
  instead of filtering in PHP.
- **Do not load what you will not show.** A menu usually needs two levels, not the whole tree.
- **Batch structural changes.** Every move is five statements and a bound shift; a hundred moves
  in a request will be felt.
- **Serialise writes per tree.** Not only for speed — see
  [Limitations](./Limitations.md#there-is-no-locking).
- **Cache the rendered tree.** Reads are cheap, but rendering is not, and the tree changes
  rarely by design.

## Counting without loading

`withCount()` works on the tree relations, so a size can be had without pulling the subtree:

```php
Category::query()->withCount('descendants')->get();   // $node->descendants_count
```

## Related

- [Limitations](./Limitations.md) — the constraints behind these numbers
- [Collections](./Collections.md) — building a tree in memory
- [Architecture](./Architecture.md) — why a write touches so many rows
