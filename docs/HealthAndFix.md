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

Each runs on its own:

```php
use Fureev\Trees\Healthy\DuplicatesCheck;

(new DuplicatesCheck(Category::class))->check();   // number of offending nodes
```

> [!WARNING]
> `HealthyChecker` runs the **first three only** — `MissingParentCheck` is commented out of its
> list. An orphaned node is therefore reported as a healthy tree. Run that check yourself when
> you suspect orphans, which is what a query-level delete leaves behind.

```php
use Fureev\Trees\Healthy\MissingParentCheck;

(new MissingParentCheck(Category::class))->check();
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

> [!WARNING]
> The `Fixing` trait carries the author's own note: *"It's not verified and tested on new
> Version 5!"*. Treat it as a last resort — take a backup, run it on a copy, and check the
> result with `HealthyChecker` before trusting it in production.

Repair cannot invent what is gone. If `parent_id` itself is wrong, or the parent row was deleted,
the rebuild has nothing correct to work from.

## Preventing the damage

Most broken trees come from three things, all avoidable:

1. **Deleting or updating through a query.** Go through the model —
   [Limitations](./Limitations.md#never-delete-through-a-query).
2. **A move interrupted halfway.** Wrap writes in a transaction; the package opens none.
3. **Concurrent writes to one tree.** Serialise them.

## Related

- [Troubleshooting](./Troubleshooting.md) — symptoms and what they mean
- [Limitations](./Limitations.md) — why these three cause the damage
