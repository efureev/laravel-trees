# Concepts

> **Read this first if the words below are new.** Everything else in the documentation uses
> them without explaining them again.

## The problem a nested set solves

Store a hierarchy in one table and you have a choice about what a row remembers.

The obvious answer is a parent link: each row names its parent. Cheap to write — one column,
one value — and hopeless to read. "Every category under Electronics, however deep" becomes a
query per level, and you do not know how many levels there are until you have walked them.

A **nested set** remembers something else: where the node sits in a walk of the whole tree.
Walk the tree from the root, number every step, and give each node the number where you entered
it and the number where you left.

```
                     root (1 .. 12)
        ┌────────────────┴────────────────┐
   phones (2 .. 7)                  laptops (8 .. 11)
        │                                 │
   android (3 .. 6)                  gaming (9 .. 10)
        │
   pixel (4 .. 5)
```

Now the hierarchy is arithmetic. Everything under `phones` is everything whose numbers fall
between 2 and 7 — one query, any depth, no recursion. Every ancestor of `pixel` is everything
whose span contains 4 and 5. Whether a node is a leaf is `right - left == 1`.

The trade is written into those numbers. Reading is cheap because the answer is a range; writing
is expensive because inserting a node has to make room, and making room means renumbering
everything positioned after it. See [Performance](./Performance.md) for what that costs.

## The vocabulary

| Word | Means |
|---|---|
| **bounds** | the two numbers, `lft` and `rgt`, that mark where the walk entered and left the node |
| **root** | a node with no parent; its span contains the whole tree |
| **leaf** | a node with nothing inside it, so `rgt - lft == 1` |
| **ancestor** | any node whose span contains this one — the parent, its parent, and so on to the root |
| **descendant** | any node inside this one's span, at any depth |
| **subtree** | a node together with its descendants — one contiguous range of numbers |
| **level** | how deep the node sits; the root is `0` |
| **tree id** | which tree a row belongs to, when one table holds several |

The package stores `lft`, `rgt` and `lvl`, and keeps `parent_id` alongside them. The parent link
is redundant for reading — the bounds already say who the parent is — but it is what the repair
in [Health and Fixing](./HealthAndFix.md) rebuilds the bounds *from* when they are damaged.

## When it fits

A nested set earns its keep when reads outnumber writes and the reads are about whole branches:

- a product catalogue rendered as a menu on every page, edited by a handful of people;
- an organisation chart, asked "everyone under this director" and reorganised now and then;
- threaded comments, where a thread is fetched whole and appended to at the end;
- a permission or category tree small enough to hold in memory, read constantly.

## When it does not

- **Writes outnumber reads.** Every insert renumbers half the tree. A parent link, or a
  materialised path, will be cheaper.
- **The tree is huge and busy at once.** Hundreds of thousands of nodes with steady writes is
  not the shape for this — one insert can rewrite a hundred thousand rows, and the package opens
  no transaction around it. See [Limitations](./Limitations.md).
- **You only ever ask about direct children.** The bounds buy you depth you are not using;
  `parent_id` on its own would do.
- **Several processes write the same tree.** There is no locking. Wrapping writes in a
  transaction is on you, and [Limitations](./Limitations.md) says what happens if you do not.

## What this package adds

The numbering scheme is old and well understood. What the package does is keep it correct while
you use ordinary Eloquent:

- **Positioning instead of arithmetic.** `appendTo()`, `prependTo()`, `insertBefore()`,
  `insertAfter()` say where a node belongs; the bounds follow. You never write `lft` yourself.
- **Bookkeeping on model events.** The renumbering happens on `saving`, `created`, `deleted` and
  the rest, so a plain `$node->save()` and `$node->delete()` keep the tree valid. The order this
  happens in is [Architecture](./Architecture.md).
- **Several trees in one table.** A tree column separates them, and every query the package
  builds carries it. See [Tree Shapes](./Basic.md).
- **Relations built from bounds.** `ancestors` and `descendants` are real Eloquent relations —
  eager-loadable, usable with `has()` and `withCount()` — rather than method calls that hide a
  query.
- **A collection that knows about trees.** `toTree()` links a flat result into parents and
  children in memory, with no further queries. See [Collections](./Collections.md).
- **Integrity checks and repair.** Six checks that answer whether the numbering still holds, and
  a rebuild from the parent links when it does not. See [Health and Fixing](./HealthAndFix.md).

## Related

- [Quick Start](./QuickStart.md) — a working tree in one pass
- [Architecture](./Architecture.md) — how the package keeps the numbering correct
- [Tree Shapes](./Basic.md) — one tree or many
- [Limitations](./Limitations.md) — what a nested set costs you
