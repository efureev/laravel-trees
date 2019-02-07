[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-trees/v/stable)](https://packagist.org/packages/efureev/laravel-trees)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-trees/downloads)](https://packagist.org/packages/efureev/laravel-trees)
[![Latest Unstable Version](https://poser.pugx.org/efureev/laravel-trees/v/unstable)](https://packagist.org/packages/efureev/laravel-trees)

[![Build Status](https://travis-ci.org/efureev/laravel-trees.svg?branch=master)](https://travis-ci.org/efureev/laravel-trees)

[![Maintainability](https://api.codeclimate.com/v1/badges/69eff0098adbf728341d/maintainability)](https://codeclimate.com/github/efureev/laravel-trees/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/69eff0098adbf728341d/test_coverage)](https://codeclimate.com/github/efureev/laravel-trees/test_coverage)

## Information
Tree structures

## Install
- `composer require efureev/laravel-trees`

## Test
`./vendor/bin/phpunit --testdox`  
or  
`composer test`


## Nodes manipulation

### makeRoot
_Create root node._

```php
$model = new Category(['id' => 1, 'name' => 'root node']);
$model->makeRoot()->save();
```
or
```php
$model = Category::create(['name' => 'root', '_setRoot' => true]);
```

### prependTo
_Add child-node into node. Insert before other children._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
$node = new Category(['name' => 'child 2.1']);
$node->prependTo($root)->save();
```

### appendTo
_Add child-node into node. Insert after all children._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
$node = new Category(['name' => 'child 2.1']);
$node->appendTo($root)->save();
```

### delete
_Delete node. if exist children - they will be attach to deleted node parent._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
$node = new Category(['name' => 'child 2.1']);
$node->appendTo($root)->save();
$node->delete();
```

### deleteWithChildren
_Delete node. if exist children - they will be attach to deleted node parent._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
$node21 = new Category(['name' => 'child 2.1']);
$node21->appendTo($root)->save();
$node31 = new Category(['name' => 'child 3.1']);
$node31->prependTo($node21)->save();

$delNodes = $node21->deleteWithChildren();
```

## Nodes queries

### isRoot()
return `bool`
```php
$node->isRoot();
```

### isChildOf()
return `bool`

### isLeaf()
return `bool`

### equalTo()
return `bool`

### parents()
return `\Illuminate\Database\Eloquent\Collection`
Return collection of parent nodes

### parent()
return `\Illuminate\Database\Eloquent\Relations\BelongsTo`
Return parent node query

### children()
return `\Illuminate\Database\Eloquent\Relations\HasMany`
Return children nodes query

## Node query builder

### root()
```php
$root = Category::root()->first();
```

### parents(int $level = null)
if `$level` is not null, then select nodes where level >= `$level`
```php
Category::parents()->get();
Category::parents(1)->get();
```

### siblings()
```php
Category::siblings()->get();
```

### prev()
```php
Category::prev()->first();
```

### next()
```php
Category::next()->first();
```

### descendants()
```php
Category::descendants()->get();
```
