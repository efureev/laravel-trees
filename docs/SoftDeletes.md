# Soft Deletes

> **A trashed node keeps its place in the tree.** That single fact explains everything else on
> this page, and it is not what most people expect.

## Setup

Add Laravel's trait as usual. The package notices it and adapts.

```php
<?php

namespace App\Models;

use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use UseTree;
    use SoftDeletes;
}
```

The migration needs the usual `deleted_at` column alongside the tree columns:

```php
Migrate::columnsFromModel($table, Category::class);
$table->softDeletes();
```

## What a soft delete does — and does not

```php
$node->delete();
```

| | Hard delete | Soft delete |
|---|---|---|
| The row | removed | kept, `deleted_at` set |
| Bounds of the node | freed, gap closed | **unchanged** |
| Children | pulled up one level | **left where they are** |
| Rest of the tree | renumbered | **untouched** |

The node stays exactly where it was in the numbering. Nothing is renumbered, no children move.
Only the global scope stops it from showing up in queries.

> [!NOTE]
> This is deliberate, and it is what makes restoring work: the node still owns its span, so
> putting it back is a matter of clearing `deleted_at`.

## Reading around trashed nodes

The relations and query helpers follow Laravel's usual rules — trashed nodes are hidden, and
`withTrashed()` brings them back:

```php
$node->descendants()->count();                 // living descendants only
$node->descendants()->withTrashed()->count();  // including trashed ones

$node->ancestors()->count();
$node->ancestors()->withTrashed()->count();
```

The same holds for `children` and `parent`; the package also ships `childrenWithTrashed` and
`parentWithTrashed` for convenience.

> [!WARNING]
> A trashed node keeps its bounds, so a parent whose only child is trashed still looks like a
> branch by its numbering while `children()->count()` reports zero. Ask the question you mean.

## Bounds keep moving

Even while trashed, a node's bounds are shifted along with the rest of the tree as siblings are
added and removed. That is what keeps a later restore correct.

```php
$trashed = Category::withTrashed()->find($id);
$before  = $trashed->leftValue();

Category::make(['title' => 'new sibling'])->prependTo($parent)->save();

Category::withTrashed()->find($id)->leftValue();   // $before + 2
```

## Restoring

| Call | Brings back |
|---|---|
| `restore()` | that node alone |
| `restoreWithParents()` | the node **and every trashed ancestor above it** |
| `restoreWithDescendants()` | the node **and everything trashed beneath it** |

```php
$node = Category::withTrashed()->find($id);

$node->restore();                  // just this one
$node->restoreWithParents();       // make it reachable from the root again
$node->restoreWithDescendants();   // bring its subtree back with it
```

`restoreWithParents()` is the one you usually want after restoring something deep: a node whose
ancestors are still trashed exists, but nothing can navigate to it.

Both helpers accept a timestamp to narrow what they touch, so a batch trashed at a known moment
can be undone without disturbing older deletions:

```php
$node->restoreWithParents($deletedAt);
```

## Deleting a subtree for real

```php
$node->deleteWithChildren();          // force, removes the rows
$node->deleteWithChildren(false);     // soft, marks the subtree
```

Two behaviours worth knowing:

- The **forced** form removes trashed descendants too — nothing is left stranded inside the gap
  it closes.
- The **soft** form leaves an already-trashed node's `deleted_at` untouched, so the original
  deletion time survives.

## The single-tree root

A single tree refuses to delete its root, softly or otherwise — it raises `DeleteRootException`,
because a tree without a root is not a tree. In a multi-tree model a root may be deleted once it
has no children.

## Related

- [Managing Nodes](./ManagingNodes.md) — deleting and its strategies
- [Limitations](./Limitations.md#a-trashed-node-keeps-its-place) — the constraint in one line
- [Retrieving Nodes](./ReceivingNodes.md) — `childrenWithTrashed`, `parentWithTrashed`
