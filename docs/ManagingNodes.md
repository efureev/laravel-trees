# Managing Nodes

Moving, promoting and deleting. Everything here goes through the model — a query-level write
skips the bookkeeping and breaks the tree.

## Moving a node

The same four methods that place a new node also move an existing one. The subtree travels with
it:

```php
$node->appendTo($newParent)->save();     // last child of the new parent
$node->prependTo($newParent)->save();    // first child
$node->insertBefore($sibling)->save();   // directly before a sibling
$node->insertAfter($sibling)->save();    // directly after
```

On a multi-tree model the target may live in another tree; the whole subtree changes tree with
it. See [Creating Nodes](./CreatingNodes.md) for what each method means.

## Reordering among siblings

```php
$node->up();     // swap with the previous sibling
$node->down();   // swap with the next sibling
```

Both return `false` when there is no sibling in that direction, and do nothing.

## Promote a node to a root

An existing node can become the root of its own tree. The subtree comes along, and the gap left
behind is closed.

```php
$node->makeRoot()->save();
$node->saveAsRoot();               // the same
$node->setTree(42)->makeRoot()->save();   // into a tree you choose
```

The node ends up with `lft = 1`, `lvl = 0` and no parent.

> [!WARNING]
> This is a multi-tree operation. On a single tree it raises *"Can not move a node as the root
> when Model is not set to MultiTree"* — a single tree has room for one root only.

## Deleting

```php
$node->delete();
```

By default the children are pulled up one level and the gap is closed.

```
before                  after deleting B
root                    root
├── A                   ├── A
└── B                   ├── C     ← pulled up
    └── C               └── D
└── D
```

To take the subtree with it:

```php
$node->deleteWithChildren();        // removes the rows
$node->deleteWithChildren(false);   // soft delete, for models using SoftDeletes
```

> [!WARNING]
> Never delete through a query. `Category::query()->whereKey($id)->delete()` removes the row
> without closing the gap, and a later correct delete cannot repair it — see
> [Limitations](./Limitations.md#never-delete-through-a-query).

Related helpers:

| Method | Does |
|---|---|
| `removeDescendants()` | delete the subtree, keep the node |
| `moveChildrenToParent()` | lift the children one level, keep the node |

## Changing what a delete does

Both halves of the delete behaviour are replaceable strategies, set on the builder.

| Point | Default | Interface |
|---|---|---|
| children of a deleted node | `MoveChildrenToParent` | `ChildrenHandler` |
| `deleteWithChildren()` | `DeleteWithChildren` | `DeleteStrategy` |

A model that refuses to delete a node with children rather than re-parenting them:

```php
use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Strategy\ChildrenHandler;
use Illuminate\Database\Eloquent\Model;

class RefuseToOrphanChildren implements ChildrenHandler
{
    public function handle(Model $model): void
    {
        throw new DeletedNodeHasChildrenException($model);
    }
}
```

Register it on the model, inside `buildTree()`:

```php
protected static function buildTree(): Builder
{
    return Builder::default()
        ->setChildrenHandlerOnDelete(RefuseToOrphanChildren::class);
}
```

The handler runs only when the node actually has children; leaves delete as usual.

## Deleting a root

A single tree refuses — `DeleteRootException`. In a multi-tree model a root can be deleted once
it has no children; with children it raises `DeletedNodeHasChildrenException`.

## What it costs

| Operation | Statements |
|---|---|
| Move inside a tree | 5 |
| Move to another tree | 5 |
| Delete a leaf | 3 |
| `up()` / `down()` | 5 |

Every one of them also shifts the bounds of the nodes positioned after the change. See
[Performance](./Performance.md).

> [!WARNING]
> The package opens no transaction. A move that fails halfway leaves the tree inconsistent —
> wrap writes yourself.

## Soft deletes

Deleting behaves differently on a model using `SoftDeletes`: the node keeps its place and its
bounds, and the children are not moved. See [Soft Deletes](./SoftDeletes.md).

## Related

- [Creating Nodes](./CreatingNodes.md) — the positioning methods in detail
- [Limitations](./Limitations.md) — transactions, concurrency, query-level writes
- [Troubleshooting](./Troubleshooting.md) — when a tree has gone wrong
