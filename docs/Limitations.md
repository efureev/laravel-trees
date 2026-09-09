# Limitations

> **Read this before you commit to the package.** Nested sets buy fast reads with expensive
> writes, and this page is the price list. Nothing here is a bug — it is the shape of the design.

## At a glance

| Area | The limit |
|---|---|
| [Transactions](#the-package-opens-no-transactions) | The package opens none. Wrapping is on you |
| [Concurrency](#there-is-no-locking) | No locking. Two parallel moves can corrupt a tree |
| [Write cost](#writes-touch-half-the-tree) | Insert, move and delete shift the bounds of everything after them |
| [Deleting](#never-delete-through-a-query) | A query-level `delete()` breaks the tree, permanently |
| [Single trees](#a-single-tree-holds-exactly-one-root) | One root only; a node cannot be promoted to a root |
| [Soft deletes](#a-trashed-node-keeps-its-place) | A trashed node keeps its bounds and its place |
| [Databases](#postgresql-is-the-tested-database) | Tested on PostgreSQL only |
| [Key types](#a-tree-id-has-to-match-its-column) | `setTree()` takes the value as given; a string in an integer column compares unequal |
| [Semantics](#names-that-mean-something-slightly-different) | `isChildOf()` means "is a descendant"; several methods reorder for you |

---

## The package opens no transactions

Moving a node runs five separate statements. Between them the tree is inconsistent:
bounds have been shifted for part of the table and not for the rest.

The package never calls `beginTransaction()` — wrapping the call is the application's job.

```php
DB::transaction(static function () use ($node, $target) {
    $node->appendTo($target)->save();
});
```

> [!WARNING]
> Without a transaction, a failure halfway through a move leaves the tree broken, and nothing
> detects it until someone reads a subtree and gets the wrong nodes back.

The readme recommends a transactional database for exactly this reason, but a transactional
database does nothing unless you open a transaction.

## There is no locking

No statement in the package takes a row or table lock. Two requests moving nodes inside the same
tree at the same time will interleave their bound shifts, and the result is not a tree.

If concurrent writes are possible in your application, serialise them yourself — a lock on the
tree id, a queue with one worker per tree, or `SELECT ... FOR UPDATE` around the operation.

## Writes touch half the tree

Nested sets number the nodes by walking the tree, so inserting or removing a node renumbers
everything positioned after it.

```
before insert          after inserting X under B
root  1 .............. 8      root  1 ................. 10
├── A  2 ... 3                ├── A  2 ... 3
└── B  4 ...... 7             └── B  4 ......... 9
    └── C  5 . 6                  ├── X  5 . 6      ← new
                                  └── C  7 . 8      ← shifted
```

| Operation | Statements | Made up of |
|---|---|---|
| Read a subtree, at any depth | **1** | one select |
| Read the ancestors of a node | **1** | one select |
| Insert a node | **3** | select the target, shift the bounds, insert |
| Move a node inside its tree | **5** | select, then four updates |
| Move a node to another tree | **5** | the same, plus the tree column travels with the subtree |
| Delete a leaf | **3** | refresh, delete, close the gap |

Counted from the query log on a live tree, not estimated.

The shift is a single `UPDATE`, but it can match a large share of the table. Nested sets suit
trees that are read far more often than they are written. A tree of hundreds of thousands of
nodes under constant edits is the wrong use for this model.

> [!TIP]
> Reads are where nested sets pay off: a whole subtree, however deep, is one query with no
> recursion.

## Never delete through a query

Deleting a node is bookkeeping, not a row removal: the gap it leaves has to be closed and its
children re-parented. That work lives in the model's events, and a query-level delete skips it.

```php
// Removes the row and leaves the tree broken
Category::query()->whereKey($id)->delete();

// Correct
Category::query()->findOrFail($id)->delete();
```

> [!WARNING]
> The damage is not repaired by deleting properly afterwards. A later delete trusts the bounds
> the query left behind, so it removes the wrong amount of space and the tree stays wrong.

To remove a node together with everything under it, use `deleteWithChildren()`.

## A single tree holds exactly one root

With a single-tree model, creating a second root raises `UniqueRootException`, and the first one
has to be created explicitly — a plain `create()` raises `NotSupportedException`, because the
package has no way to tell where the node belongs.

```php
Category::make($attributes)->makeRoot()->save();
Category::make($attributes)->saveAsRoot();
```

Promoting an existing node to a root is a multi-tree operation. On a single tree it raises
`Can not move a node as the root when Model is not set to "MultiTree"`, since there is no second
tree for it to become the root of.

## A trashed node keeps its place

Soft deleting does not renumber anything. The node keeps its bounds and stays where it was; only
the `deleted_at` column changes and the global scope hides it from queries.

That has consequences worth knowing:

- the gap is **not** closed, and the children are **not** moved up;
- the bounds of a trashed node keep shifting along with the rest of the tree, so restoring it
  puts it back in the right place;
- `deleteWithChildren()` on a soft-deleting model removes the subtree for real when forced, and
  that does take trashed descendants with it.

## PostgreSQL is the tested database

The suite runs against PostgreSQL only, in CI and in the bundled Docker setup. MySQL appears in
one code path but no test exercises it. Other engines are unverified.

## A tree id has to match its column

`setTree()` stores what it is handed, without casting it to the column's type. Reading it back
goes through the cast, so a value of the wrong type is written one way and read another:

```php
$node->setTree('777');   // integer column
$node->treeValue();      // 777, as an int
```

`isEqualTo()` compares tree values strictly, so a node written with `'777'` can compare unequal
to one written with `777` until both have been read back from the database. Pass the type the
column holds — an int for an integer column, a string for a UUID or ULID one.

The package does not cast on the way in on purpose: doing so would mean reading the model's
casts on every write, on the hot path, to catch a mistake in the call.

## Names that mean something slightly different

| Name | What it actually does |
|---|---|
| `isChildOf($node)` | True for a descendant at **any** depth, not only a direct child — it compares bounds. Kept under its historical name; `isDescendantOf()` is the same check, `isDirectChildOf()` the one level down |
| `orderBy()` after `children()`, `parents()` or `whereAncestorOf()` | Kept, but below `lft`. `lft` is unique within a tree, so the tie never happens and the ordering never applies — `reorder()` first |

> [!NOTE]
> `Fureev\Trees\Contracts\TreeModel` describes a node for static analysis; nothing implements it
> and nothing tests for it, so declaring it on a model of your own gains nothing. What makes a
> model a node is the `UseTree` trait, which is what `Helper::isTreeNode()` looks for.

## Related

- [Performance](./Performance.md) — what each operation costs and how to keep it down
- [Managing Nodes](./ManagingNodes.md) — the correct way to move and delete
- [Health Checks and Fixing](./HealthAndFix.md) — detecting and repairing a broken tree
