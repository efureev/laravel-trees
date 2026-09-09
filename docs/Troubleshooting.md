# Troubleshooting

Symptoms first. Find the one that matches, follow it to the cause.

## The node was never saved

**Symptom.** You called `appendTo()` / `prependTo()` / `makeRoot()` and nothing changed in the
database.

**Cause.** Those methods record an intent and return the model. They issue no query at all — the
work happens on the next `save()`.

```php
$node->appendTo($parent);          // nothing happened
$node->appendTo($parent)->save();  // this is the whole operation
```

See [Architecture](./Architecture.md#operations-are-deferred).

## `NotSupportedException` when creating a node

**Symptom.** `Category::create([...])` throws *Not Supported* on a single-tree model.

**Cause.** A single tree cannot guess where a new node belongs. The first node has to be
declared the root, and every other node has to be positioned:

```php
Category::make($attributes)->makeRoot()->save();   // the root
Category::make($attributes)->appendTo($parent)->save();
```

Multi-tree models accept a plain `create()` — the node becomes the root of a new tree.

## `UniqueRootException`

**Symptom.** Creating a second root fails.

**Cause.** The model is configured as a single tree, which holds exactly one root. Use
`Builder::defaultMulti()` if you need several — see [Basic Usage](./Basic.md).

## "Can not move a node as the root when Model is not set to MultiTree"

**Cause.** Promoting an existing node to a root means giving it a tree of its own, which a
single-tree model has no room for. The operation exists only for multi-trees.

## `DeleteRootException`

**Cause.** A single tree refuses to delete its root — a tree with no root is not a tree. In a
multi-tree model a root can be deleted once it has no children.

## `DeletedNodeHasChildrenException`

**Cause.** You are deleting a root that still has children. Move or delete them first, or use
`deleteWithChildren()` to take the subtree with it.

## A relation comes back empty on a multi-tree model

**Symptom.** `$node->descendants` or `$node->ancestors` returns nothing, though the tree clearly
has nodes.

**Cause.** Every tree is numbered from `lft = 1`, so bounds alone cannot tell trees apart, and
every query the package builds is scoped by the tree column. If the node's `tree_id` is not what
you think it is, the relation is looking in the wrong tree.

```php
$node->treeValue();   // which tree does this node actually belong to?
```

## `isRoot()` says false on something that looks like a root

**Cause.** `isRoot()` asks one question: is `parent_id` null. A node sitting at `lft = 1` with
`lvl = 0` but a leftover parent is not a root by that definition.

## The tree returns the wrong nodes

**Symptom.** Subtrees contain nodes that do not belong, counts are off, a node appears twice.

**Cause.** The bounds have drifted. The usual reasons, in order of likelihood:

1. **A node was deleted or updated through a query.** This is by far the most common. A
   query-level `delete()` skips the model events, so the gap is never closed.
   See [Limitations](./Limitations.md#never-delete-through-a-query).
2. **A move failed halfway.** Moving a node takes five statements and the package opens no
   transaction, so a failure in the middle leaves the tree inconsistent.
3. **Concurrent writes.** Two moves in the same tree at once will interleave.

## Diagnosing a broken tree

```php
use Fureev\Trees\Healthy\HealthyChecker;

$checker = new HealthyChecker(Category::class);

$checker->isBroken();   // bool
$checker->check();      // ['OddnessCheck' => 0, 'DuplicatesCheck' => 2, 'WrongParentCheck' => 0]
```

The four checks and what each one finds:

| Check | Finds |
|---|---|
| `OddnessCheck` | nodes whose `lft`/`rgt` cannot be a valid pair |
| `DuplicatesCheck` | nodes sharing a bound value with another node |
| `WrongParentCheck` | nodes whose `parent_id` disagrees with their bounds |
| `MissingParentCheck` | nodes whose `parent_id` points at a row that no longer exists |
| `RangeCheck` | trees whose numbering has holes — bounds vacated and never reclaimed |
| `RootCheck` | trees that do not have exactly one root |

> [!NOTE]
> `RangeCheck` and `RootCheck` answer with a count of **trees**; the rest count nodes.

Category::fixTree();        // single tree
Category::fixMultiTree();   // every tree in the table
```

> [!WARNING]
> The repair trait carries the author's own note that it has not been verified since v5. Back up
> first, run it on a copy, and check the result with `HealthyChecker` before trusting it.

See [Health Checks and Fixing](./HealthAndFix.md).

## Custom column names break the SQL

**Symptom.** `column "leftbound" does not exist`, or similar, on a move or delete.

**Cause.** This was a defect in the package, fixed by routing every raw identifier through the
query grammar. If you see it, you are on an older release — upgrade.

## Related

- [Limitations](./Limitations.md) — what the package will not do for you
- [Architecture](./Architecture.md) — why the events matter
- [Health Checks and Fixing](./HealthAndFix.md) — the checks in detail
