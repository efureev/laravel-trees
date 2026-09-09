# Tree Shapes

> **Decide this first.** Everything else — how roots are created, whether nodes can move between
> trees, what a query is scoped by — follows from which of the two shapes you pick.

## Single tree

One root, everything else beneath it. No tree column.

```php
<?php

namespace App\Models;

use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use UseTree<static> */
    use UseTree;
}
```

Adding the trait is the whole configuration. Use this when the data has one natural top: a
product catalogue, a menu, a single organisation.

## Multi-tree

Several independent trees in one table, told apart by a tree column.

```php
<?php

namespace App\Models;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use UseTree<static> */
    use UseTree;

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti();
    }
}
```

Use this when the data has many independent tops: a catalogue per tenant, an org chart per legal
entity, a menu per site.

## What changes between them

| | Single tree | Multi-tree |
|---|---|---|
| Roots | exactly one | one per tree |
| Extra column | — | `tree_id` |
| Creating a root | must be explicit — `makeRoot()` or `saveAsRoot()` | a plain `create()` starts a new tree |
| A second root | `UniqueRootException` | ordinary |
| Promoting a node to a root | not possible — raises an exception | supported, the subtree comes along |
| Moving a node to another tree | nothing to move to | supported |
| Query scoping | nothing to scope | every query carries the tree condition |

> [!NOTE]
> Every tree numbers its bounds from `lft = 1`, so two trees hold the same numbers. The tree
> column is the only thing keeping them apart — which is why the package adds it to every query
> it builds, and why raw bound queries of your own must add it too.

## Changing shape later

Going from single to multi means adding the tree column and giving the existing rows a value.
The bounds themselves do not change: an existing tree simply becomes tree number one.

Going the other way is only safe when a single tree is left.

## Related

- [Quick Start](./QuickStart.md) — a working tree in one pass
- [Advanced Tree Configuration](./AdvancedTreeConfig.md) — custom column names, UUID and ULID keys
- [Use Cases](./UseCases.md) — which shape fits which problem
