# Advanced Tree Config

You can change or redefine default settings:

```php
<?php
namespace App\Models;

class Category extends Model
{
    use Fureev\Trees\UseTree;
    
    protected static function buildTree(): Builder
    {
        return Builder::make()
            ->setAttributes(
                Attribute::make(AttributeType::Left),
                Attribute::make(AttributeType::Right),
                Attribute::make(AttributeType::Level),
                Attribute::make(AttributeType::Parent),
                // Attribute::make(AttributeType::Tree)->setColumnName('tid'),
            )
    }
}
```

Or only You need

```php
<?php
namespace App\Models;

class Category extends Model
{
    use Fureev\Trees\UseTree;
    
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

class Category extends Model
{
    use Fureev\Trees\UseTree;
    
    protected $keyType = 'uuid';
    
    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()->setAttribute(Attribute::make(AttributeType::Tree, FieldType::UUID));
    }
}
```
