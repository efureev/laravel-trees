# Advanced Tree Config

You can change or redefine default settings:

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
                Attribute::make(AttributeType::Left),
                Attribute::make(AttributeType::Right),
                Attribute::make(AttributeType::Level),
                Attribute::make(AttributeType::Parent),
                // Attribute::make(AttributeType::Tree)->setColumnName('tid'),
            );
    }
}
```

`Builder::make()` starts from nothing, so every attribute has to be listed. Adding a `Tree`
attribute is what turns the model into a multi-tree one.

Or only You need

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

## Setting up Primary Key and TreeId Type

- Primary Key: UUID
- TreeId: UUID

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

    protected $keyType = 'uuid';

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()->setAttribute(Attribute::make(AttributeType::Tree, FieldType::UUID));
    }
}
```

> The trait has to be imported at the top of the file. `use Fureev\Trees\UseTree;` written inside
> the class body resolves against the current namespace and fails with
> `Trait "App\Models\Fureev\Trees\UseTree" not found`.
