# Performance

> **Nested sets trade write cost for read cost.** Reads are cheap and flat; writes renumber part
> of the table. This page puts numbers on both so the trade is a decision, not a surprise.

## What each operation costs

Statement counts measured from the query log on a live tree, not estimated — the two
`DocumentedPerformanceCountsTest` classes, one per tree shape, assert every number below, so a
change in cost fails a test instead of ageing this page:

| Operation | Statements | What runs |
|---|---|---|
| Read a subtree, any depth | **1** | one `select` over a bound range |
| Read the ancestors of a node | **1** | one `select`, ordered root first |
| Read direct children | **1** | one `select` on `parent_id` |
| Insert a node under a parent | **3** | select the target, shift the bounds, insert |
| Insert a root | **1–2** | the `insert`, plus the select that picks the next tree id — or, on a single-tree model, the one that checks no root is there yet |
| Move a node inside its tree | **5** | one select, four updates |
| Move a node to another tree | **5** | the same, and the tree column travels with the subtree |
| Promote a node to a root | **3–4** | re-parent the row, move the subtree into a tree of its own, close the gap it left; a generated tree id costs one select more |
| Delete a leaf | **3** | refresh, delete, close the gap |

> [!NOTE]
> Five is what a move costs when it moves. Three cases differ:
> - `up()` and `down()` cost **6** — one select more, to find the sibling to swap with;
> - a move to a place the node already borders costs **4**: nothing lies between it and the
>   target, so the shift matches no rows and is skipped;
> - re-positioning under the current parent sets no attribute, so `save()` finds the model
>   clean, writes **nothing at all**, and the node stays where it was. `forceSave()` is what
>   carries that move through — it is why `up()` and `down()` use it.

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

A move costs four updates rather than two because the subtree has to sit out the shift that
makes room for it: its rows are marked by flipping the sign of their level, everything else is
renumbered around them, and the marked rows are then moved into the gap and unmarked.

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

- **Limit the depth you read.** `descendantsQuery($level)` bounds the range and the level in
  SQL, `toLevel($level)` adds the level to a query you already have — neither pulls rows into
  PHP to drop them there.
- **Do not load what you will not show.** A menu usually needs two levels, not the whole tree.
- **Batch structural changes.** A move is five statements and a bound shift; a hundred moves
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
