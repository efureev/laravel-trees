# Migration Guide

## From v5 to v6

`v6` only raises the minimum platform requirements — there are **no breaking API changes**.

### Requirements

- `PHP >= 8.4` (previously `8.2`)
- `Laravel >= 13` (`illuminate/*: ^13.0`; support for Laravel 11/12 was dropped)

### Steps

1. Make sure your application runs on PHP 8.4+ and Laravel 13+.
2. Update the dependency:

   ```shell
   composer require efureev/laravel-trees:^6.0
   ```

3. No code changes are required: the public API (`UseTree`, `Builder`, `Migrate`, query builder methods, etc.)
   is unchanged.

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
