# Creating Nodes

A node is an ordinary Eloquent model. What makes it a tree node is that it always has a
**position**, and the position has to be stated when it is created.

## The root

A root is the node with no parent. How it is created depends on the
[tree shape](./Basic.md).

**Single tree** — say it outright. There is no other node to hang it from, so the package will
not guess:

```php
Category::make($attributes)->makeRoot()->save();
Category::make($attributes)->saveAsRoot();     // the same thing
```

> [!WARNING]
> `Category::create($attributes)` on a single-tree model raises `NotSupportedException`, and a
> second root raises `UniqueRootException`.

**Multi-tree** — a node with no parent starts a new tree, so the ordinary Eloquent call works:

```php
Category::create($attributes);
Category::make($attributes)->save();
```

To choose the tree id instead of having one generated:

```php
Category::make($attributes)->setTree($tenantId)->makeRoot()->save();
```

## Everything else

Every non-root node is positioned relative to an existing one. Four ways, differing in where the
new node lands:

| Method | Takes | Puts the node |
|---|---|---|
| `prependTo($parent)` | a **parent** | first among that parent's children |
| `appendTo($parent)` | a **parent** | last among that parent's children |
| `insertBefore($sibling)` | a **sibling** | directly before it, same parent |
| `insertAfter($sibling)` | a **sibling** | directly after it, same parent |

Applied one after another to the same tree:

```php
$b->appendTo($root)->save();        // root, B
$a->prependTo($root)->save();       // root, A, B
$mid->insertAfter($a)->save();      // root, A, MID, B
$first->insertBefore($a)->save();   // root, FIRST, A, MID, B
```

> [!WARNING]
> `insertBefore()` and `insertAfter()` take a **sibling**, not a parent. Passing a root raises
> `UniqueRootException` — "Can not insert a node before/after root" — because a sibling of the
> root would be a second root.

## Nothing happens until you save

The positioning call records where the node belongs and returns the model. It issues no query.

```php
$node->appendTo($parent);          // no query, nothing persisted
$node->appendTo($parent)->save();  // the node is created and the tree makes room for it
```

This is the most common way to lose a node. See
[Architecture](./Architecture.md#operations-are-deferred).

## What it costs

Creating a node is three statements: read the target, shift the bounds to make room, insert.
The shift is one `UPDATE`, but it touches every node positioned after the new one — see
[Performance](./Performance.md).

## Related

- [Managing Nodes](./ManagingNodes.md) — moving and deleting what you created
- [Tree Shapes](./Basic.md) — single versus multi
- [Quick Start](./QuickStart.md) — the whole flow end to end
