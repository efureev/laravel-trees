[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-trees/version)](https://packagist.org/packages/efureev/laravel-trees)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-trees/downloads)](https://packagist.org/packages/efureev/laravel-trees)
[![Latest Unstable Version](https://poser.pugx.org/efureev/laravel-trees/v/unstable)](https://packagist.org/packages/efureev/laravel-trees)
[![License](https://poser.pugx.org/efureev/laravel-trees/license)](https://packagist.org/packages/efureev/laravel-trees)
[![composer.lock available](https://poser.pugx.org/efureev/laravel-trees/composerlock)](https://packagist.org/packages/efureev/laravel-trees)

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

### insertBefore
_Add child-node into same parent node. Insert before target node._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
$node2 = new Category(['name' => 'child 2.1']);
$node2->appendTo($root)->save();

$node3 = new Category(['name' => 'child 3.1']);
$node3->insertBefore($node2)->save();
```

### insertAfter
_Add child-node into same parent node. Insert after target node._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
$node2 = new Category(['name' => 'child 2.1']);
$node2->appendTo($root)->save();

$node3 = new Category(['name' => 'child 3.1']);
$node3->insertAfter($node2)->save();
```

### up
_Move node up in self parent scope._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
(new Category(['name' => 'child 2']))->appendTo($root)->save();
(new Category(['name' => 'child 3']))->appendTo($root)->save();
$node = new Category(['name' => 'child 4']);
$node->appendTo($root)->save();
$node->up();
```

### down
_Move node down in self parent scope._

```php
$root = Category::create(['name' => 'root', '_setRoot' => true]);
(new Category(['name' => 'child 2']))->appendTo($root)->save();
(new Category(['name' => 'child 3']))->appendTo($root)->save();
$node = new Category(['name' => 'child 4']);
$node->appendTo($root)->save();
$node->down();
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
*Return collection of parent nodes*  
return `\Illuminate\Database\Eloquent\Collection`

### parent()
*Return parent node query*  
return `\Illuminate\Database\Eloquent\Relations\BelongsTo`

### children()
*Return children nodes query*  
return `\Illuminate\Database\Eloquent\Relations\HasMany`

## Node query builder

### root()
```php
$root = Category::root()->first();
```

### parents(int $level = null)
*if `$level` is not null, then select nodes where level >= `$level`*  
return `\Fureev\Trees\QueryBuilder`
```php
Category::parents()->get();
Category::parents(1)->get();
```

### siblings()
return `\Fureev\Trees\QueryBuilder`
```php
Category::siblings()->get();
```

### prev()
return `\Fureev\Trees\QueryBuilder`
```php
Category::prev()->first();
```

### next()
return `\Fureev\Trees\QueryBuilder`
```php
Category::next()->first();
```

### prevSiblings()
return `\Fureev\Trees\QueryBuilder`
```php
$model->prevSiblings()->get();
```

### nextSiblings()
return `\Fureev\Trees\QueryBuilder`
```php
$model->nextSiblings()->get();
```

### prevSibling()
return `\Fureev\Trees\QueryBuilder`
```php
$model->prevSibling()->first();
```

### nextSibling()
return `\Fureev\Trees\QueryBuilder`
```php
$model->nextSibling()->first();
```

### descendants()
return `\Fureev\Trees\QueryBuilder`
```php
Category::descendants()->get();
```
