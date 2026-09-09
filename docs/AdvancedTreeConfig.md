# Advanced Tree Configuration

The defaults suit most tables. Override `buildTree()` when they do not.

```php
protected static function buildTree(): Builder
{
    // ...
}
```

The configuration is read once, when the model is initialised, and frozen — see
[Architecture](./Architecture.md#configuration-is-built-once).

## Starting points

| Call | Gives |
|---|---|
| `Builder::default()` | single tree with the default columns |
| `Builder::defaultMulti()` | the same plus the tree column |
| `Builder::make()` | nothing preset — every attribute must be supplied |

## Renaming a column

Change one and leave the rest alone:

```php
<?php

namespace App\Models;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use UseTree;

    protected static function buildTree(): Builder
    {
        $builder = Builder::defaultMulti();
        $builder->tree()->setColumnName('tid');

        return $builder;
    }
}
```

`left()`, `right()`, `level()`, `parent()` and `tree()` each return the `Attribute` describing
that column, so any of them can be renamed the same way.

Declaring the whole set explicitly instead:

```php
<?php

namespace App\Models;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\Builder;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use UseTree;

    protected static function buildTree(): Builder
    {
        return Builder::make()
            ->setAttributes(
                Attribute::make(AttributeType::Left)->setColumnName('left_bound'),
                Attribute::make(AttributeType::Right)->setColumnName('right_bound'),
                Attribute::make(AttributeType::Level)->setColumnName('depth'),
                Attribute::make(AttributeType::Parent)->setColumnName('pid')->setNullable(),
                // add a Tree attribute to make it a multi-tree model
            );
    }
}
```

> [!WARNING]
> Import the trait at the top of the file. `use Fureev\Trees\UseTree;` written inside the class
> body resolves against the current namespace and fails with
> `Trait "App\Models\Fureev\Trees\UseTree" not found`.

Renamed columns are quoted everywhere the package builds SQL, so names needing quotes — mixed
case, reserved words — are safe.

## Column types

`FieldType` decides both the migration column and the model cast:

| `FieldType` | Migration column | Cast |
|---|---|---|
| `UnsignedSmallInteger` | `unsignedSmallInteger` | `integer` |
| `UnsignedMediumInteger` | `unsignedMediumInteger` | `integer` |
| `UnsignedInteger` (default) | `unsignedInteger` | `integer` |
| `UnsignedBigInteger` | `unsignedBigInteger` | `integer` |
| `UUID` | `uuid` | `string` |
| `ULID` | `ulid` | `string` |

## Key types

The parent column follows the model's own key type — nothing to configure. UUID and ULID keys
work through Laravel's traits:

```php
class Category extends Model
{
    use UseTree;
    use HasUuids;      // or HasUlids
}
```

## Tree identifier type

Independent of the primary key. A UUID tree id on integer-keyed rows is fine:

```php
<?php

namespace App\Models;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use UseTree;

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()
            ->setAttribute(Attribute::make(AttributeType::Tree, FieldType::UUID));
    }
}
```

The identifier is generated when a root is created without one: `max(tree_id) + 1` for integers,
UUID v7 or a lowercase ULID otherwise. Set it yourself with `setTree()` — including `0`, which
the generator never produces.

## Delete behaviour

What happens to the children of a deleted node is a strategy set on the builder. See
[Managing Nodes](./ManagingNodes.md#changing-what-a-delete-does).

## Related

- [Database Migration](./Migration.md) — the schema that matches this configuration
- [Tree Shapes](./Basic.md) — single versus multi
