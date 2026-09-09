# Health Checks and Fixing

> **Bounds can drift.** A crashed move, a manual `UPDATE`, an import, a delete that went through
> a query — any of these leaves the numbering inconsistent while the rows look fine.

## Checking

```php
use Fureev\Trees\Healthy\HealthyChecker;

$checker = new HealthyChecker(Category::class);

$checker->isBroken();        // bool
$checker->getTotalErrors();  // int
$checker->check();           // ['OddnessCheck' => 0, 'DuplicatesCheck' => 2, 'WrongParentCheck' => 0]
```

## The four checks

| Check | Counts nodes that |
|---|---|
| `OddnessCheck` | have an impossible `lft`/`rgt` pair — `lft >= rgt`, or a span of even width |
| `DuplicatesCheck` | share a bound value with another node in the same tree |
| `WrongParentCheck` | have a `parent_id` that disagrees with where their bounds place them |
| `MissingParentCheck` | have a `parent_id` pointing at a row that no longer exists |
| `RangeCheck` | count the trees whose numbering has holes — bounds vacated and never reclaimed |
| `RootCheck` | count the trees that do not have exactly one root |

`HealthyChecker` runs all six.

Each runs on its own, and each answers with a count of **nodes**:

```php
use Fureev\Trees\Healthy\DuplicatesCheck;

(new DuplicatesCheck(Category::class))->check();   // number of offending nodes
```

> [!NOTE]
> `WrongParentCheck` compares each node with the parent its `parent_id` names: the parent has to
> enclose it and sit exactly one level above. A level that does not line up means something is
> between them, or that the level itself is wrong.

The checks group and join on equality, so they scan rather than compare every node with every
other. A hundred thousand nodes take about 50 ms.

> [!NOTE]
> `RangeCheck` and `RootCheck` answer with a count of **trees**, not of nodes — a hole in the
> numbering and a missing root belong to the tree, and no single node is to blame.

Pass the model itself rather than its class name when it lives on a connection of its own, or
when `getScopeAttributes()` narrows its queries by attributes the check has to see:

```php
(new HealthyChecker($node))->check();
```

## Fixing

The repair rebuilds the bounds from the `parent_id` links, which survive most kinds of damage.

```php
Category::fixTree();              // a single tree
Category::fixTree($root);         // one root's tree
Category::query()->fixSubTree($node);   // one subtree
Category::fixMultiTree();         // every tree in the table
```

Each returns the number of nodes it changed; `fixMultiTree()` returns one count per tree.

> [!IMPORTANT]
> Repair rewrites the bounds of every node it touches, so it is still a last resort rather than
> routine maintenance: take a backup and check the result with `HealthyChecker`. What it is no
> longer is untested — an orphan, a cycle of parent links, a tree with no root at all, and
> several trees at once are all covered.

A node whose parent row is gone, or one caught in a cycle of parent links, is attached to the
root of the tree being repaired. Neither can be placed from its own link, and the bounds are the
damage being repaired, so the root is the one place known to be there.

Repair cannot invent what is gone. If `parent_id` points at the wrong node, the rebuild follows
it, because that link is all it has.

## Preventing the damage

Most broken trees come from three things, all avoidable:

1. **Deleting or updating through a query.** Go through the model —
   [Limitations](./Limitations.md#never-delete-through-a-query).
2. **A move interrupted halfway.** Wrap writes in a transaction; the package opens none.
3. **Concurrent writes to one tree.** Serialise them.

## Related

- [Troubleshooting](./Troubleshooting.md) — symptoms and what they mean
- [Limitations](./Limitations.md) — why these three cause the damage
