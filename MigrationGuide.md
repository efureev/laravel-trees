# Migration Guide

## From v4 to v5

### Migrations

old

```php
Migrate::columns($table, (new Category())->getTreeConfig());
```

new

```php
Migrate::columnsFromModel($table, Category::class);
```

### Models

old

```php
class Category extends Model
{
    use NestedSetTrait;
    
    protected static function buildTreeConfig(): Base
    {
        return new Base(TreeAttribute::make()->setUuidType()->setName('group_id')->setAutoGenerate(false));
    }
}
```

new

```php
class Category extends Model
{
    use UseTree;
    
     protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()
            ->setAttribute(
                Attribute::make(AttributeType::Tree, FieldType::UUID)
                    ->setColumnName('group_id')
            );
    }
}
```
