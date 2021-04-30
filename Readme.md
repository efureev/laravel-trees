![PHP Laravel Package](https://github.com/efureev/laravel-trees/workflows/PHP%20Laravel%20Package/badge.svg?branch=master)
![](https://img.shields.io/badge/php-^8.0-blue.svg)
![](https://img.shields.io/badge/Laravel-^8.38-red.svg)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-trees/downloads)](https://packagist.org/packages/efureev/laravel-trees)
[![Latest Unstable Version](https://poser.pugx.org/efureev/laravel-trees/v/unstable)](https://packagist.org/packages/efureev/laravel-trees)
[![License](https://poser.pugx.org/efureev/laravel-trees/license)](https://packagist.org/packages/efureev/laravel-trees)
[![composer.lock available](https://poser.pugx.org/efureev/laravel-trees/composerlock)](https://packagist.org/packages/efureev/laravel-trees)

[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-trees/version)](https://packagist.org/packages/efureev/laravel-trees)
[![Build Status](https://travis-ci.org/efureev/laravel-trees.svg?branch=master)](https://travis-ci.org/efureev/laravel-trees)

[![Maintainability](https://api.codeclimate.com/v1/badges/69eff0098adbf728341d/maintainability)](https://codeclimate.com/github/efureev/laravel-trees/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/69eff0098adbf728341d/test_coverage)](https://codeclimate.com/github/efureev/laravel-trees/test_coverage)


__Contents:__

- [Theory](#information)
- [Requirements](#requirements)
- [Installation](#installation)
- [Testing](#testing)
- [Documentation](#documentation)
    -   [Migrating](#migrating)
    -   [Relationships](#relationships)
    -   [Creating nodes](#creating-nodes)
    -   [Moving nodes](#moving-nodes)
    -   [Deleting nodes](#deleting-nodes)
    -   [Retrieving nodes](#retrieving-nodes)
    -   [Nodes queries](#nodes-queries)
    -   [Model's helpers](#models-helpers)


Information
--------------
This package is Multi-Tree structures (a lot of root-nodes).

### What are nested sets?

Nested sets or [Nested Set Model](http://en.wikipedia.org/wiki/Nested_set_model) is
a way to effectively store hierarchical data in a relational table. From wikipedia:

> The nested set model is to number the nodes according to a tree traversal,
> which visits each node twice, assigning numbers in the order of visiting, and
> at both visits. This leaves two numbers for each node, which are stored as two
> attributes. Querying becomes inexpensive: hierarchy membership can be tested by
> comparing these numbers. Updating requires renumbering and is therefore expensive.

### Applications

NSM shows good performance when tree is updated rarely. It is tuned to be fast for
getting related nodes. It'is ideally suited for building multi-depth menu or
categories for shop.



Requirements
------------

- PHP >= 8.0
- Laravel >= 8.38

It is highly suggested to use database that supports transactions (like MySql's InnoDb, Postgres)
to secure a tree from possible corruption.

Installation
------------

To install the package, in terminal:

```
composer require efureev/laravel-trees
```

Testing
------------
```
./vendor/bin/phpunit --testdox
```
or 
```
composer test
```


Documentation
-------------
This package works with different model primary key: `int`, `uuid`.
This package allows to creating multi-root structures: no only-one-root! And allow to move nodes between trees.   
 
### Migrating

**Model for Single tree structure:**
```php
<?php
namespace App\Models;

use Fureev\Trees\NestedSetTrait;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use NestedSetTrait;

}
```
or with custom base config
```php
<?php
namespace App\Models;

use Fureev\Trees\{NestedSetTrait,Contracts\TreeConfigurable};
use Fureev\Trees\Config\Base;
use Illuminate\Database\Eloquent\Model;

class Category extends Model implements TreeConfigurable
{
    use NestedSetTrait;

    protected static function buildTreeConfig(): Base
    {
        return new Base();
    } 
}
```

or with custom config
```php
    protected static function buildTreeConfig(): Base
    {
        return Base::make()
            ->setAttribute('parent', ParentAttribute::make()->setName('papa_id'))
            ->setAttribute('left', LeftAttribute::make()->setName('left_offset'))
            ->setAttribute('right', RightAttribute::make()->setName('right_offset'))
            ->setAttribute('level', LevelAttribute::make()->setName('deeeeep'));
    }
``` 

**Model for Multi tree structure and with primary key type `uuid`:**
```php
<?php
namespace App\Models;

// use Fureev\Trees\Config\TreeAttribute;
use Fureev\Trees\Contracts\TreeConfigurable;
use Fureev\Trees\NestedSetTrait;
use Fureev\Trees\Config\Base;
use Illuminate\Database\Eloquent\Model;

class Item extends Model implements TreeConfigurable
{
    use NestedSetTrait;
    
    protected $keyType = 'uuid';

    protected static function buildTreeConfig(): Base
    {
        $config= new Base(true);
        // $config->parent()->setType('uuid'); <-- `parent type` set up automatically from `$model->keyType`

        return $config;
    }
    /*
    or:
     
    protected static function buildTreeConfig(): Base
    {
        return Base(TreeAttribute::make('uuid')->setAutoGenerate(false));
    }
    
    or:
     
    protected static function buildTreeConfig(): Base
    {
       return Base::make()
            ->setAttributeTree(TreeAttribute::make()->setName('big_tree_id'))
            ->setAttribute('parent', ParentAttribute::make()->setName('pid'))
            ->setAttribute('left', LeftAttribute::make()->setName('left_offset'))
            ->setAttribute('right', RightAttribute::make()->setName('right_offset'))
            ->setAttribute('level', LevelAttribute::make()->setName('deeeeep'));
    }
    */
}
```

Use in migrations:
```php
<?php
use Fureev\Trees\Migrate;
use Illuminate\Database\Migrations\Migration;

class AddTemplates extends Migration
{
    public function up()
    {
        Schema::create('trees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
    
            Migrate::columns($table, (new Page)->getTreeConfig());
            // or 
            // Migrate::getColumnsFromModel($table, Page::class);
    
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
```



### Relationships

Node has following relationships that are fully functional and can be eagerly loaded:

-   Node belongs to `parent`
-   Node has many `children`
-   Node has many `ancestors`
-   Node has many `descendantsNew`



### Creating nodes

#### Creating root-nodes

When you creating a root-node: If you use ... 

- single-mode: you may to create ONLY one root-node.
- multi-mode: it will be insert as root-node and different `tree_id`. Default: increment by one. You may customize this function.

These actions are identical: 
```php
// For single-root tree
Category::make($attributes)->makeRoot()->save(); 
Category::make($attributes)->saveAsRoot();
Category::create(['setRoot'=>true,...]);

// For multi-root tree. If parent is absent, node set as root.
Page::make($attributes)->save();
```

#### Creating non-root-nodes

When you creating a non-root node, it will be appended to the end of the parent node.

If you want to make node a child of other node, you can make it last or first child.

_In following examples, `$parent` is some existing node._

##### Appending to the specified parent
_Add child-node into node. Insert after other children of the parent._

```php
$node->appendTo($parent)->save();
```


##### Prepending to the specified parent
_Add child-node into node. Insert before other children of the parent._

```php
$node->prependTo($parent)->save();
```


##### Insert before parent node
_Add child-node into same parent node. Insert before target node._

```php
$node->insertBefore($parent)->save();
```

##### Insert after parent node
_Add child-node into same parent node. Insert after target node._

```php
$node->insertAfter($parent)->save();
```



### Moving nodes

##### Move node up in self parent scope

```php
$node->up();
```

##### Move node down in self parent scope

```php
$node->down();
```

### Deleting nodes

To delete a node:

```php
$node->delete();
```

**IMPORTANT!** if deleting node has children - they will be attach to deleted node parent. This behavior may be changed.

**IMPORTANT!** Nodes are required to be deleted as models! **DO NOT** try do delete them using a query like so:

```php
Category::where('id', '=', $id)->delete();
```

This will break the tree!

`SoftDeletes` trait is supported, also on model level.

Also you may to delete all children:

```php
$node->deleteWithChildren();
```


### Retrieving nodes

*In some cases we will use an `$id` variable which is an id of the target node.*


#### Ancestors and descendants

Ancestors make a chain of parents to the node. Helpful for displaying breadcrumbs
to the current category.

Descendants are all nodes in a sub tree, i.e. children of node, children of
children, etc.

Both ancestors and descendants can be eagerly loaded.

It's relationships:
- `ancestors`: AncestorsRelation
- `descendantsNew`: DescendantsRelation
- `children`: HasMany
- `parent`: BelongsTo


```php
// Accessing ancestors
$node->ancestors;

// Accessing descendants
$node->descendantsNew;

// Accessing descendants
$node->children;
```

#### Parent
Get parent node
```php
$node->parent;
```

Collection of parents
```php
$node->parents($level);
```


#### Siblings

Siblings are nodes that have same parent.

```php
// Get all siblings of the node
$collection = $node->siblings()->get();

// Get siblings which are before the node
$collection = $node->prevSiblings()->get();

// Get siblings which are after the node
$collection = $node->nexrSiblings()->get();

// Get a sibling that is immediately before the node
$prevNode = $node->prevSibling()->first();

// Get a sibling that is immediately after the node
$nextNode = $node->nextibling()->first();
```

```php
$prevNode = $node->prev()->first();
$nextNode = $node->next()->first();
```


### Nodes queries
Method | Example | Description
:--- |  :---|  :---
parents(int $level = null)  | `$node->parents(2)->get();`| Select chain of parents
root()  | `$node->root()->get();` | Select only root nodes
notRoot()  | `$node->notRoot()->get();` | Select only not root nodes
siblings()  | `$node->siblings()->get();`
siblingsAndSelf()  | `$node->siblingsAndSelf()->get();`
prev()  | `$node->prev()->first();`
next()  | `$node->next()->first();`
prevSiblings()  | `$node->prevSiblings()->get();`
nextSiblings()  | `$node->nextSiblings()->get();`
prevSibling()  | `$node->prevSibling()->first();`
nextSibling()  | `$node->nextSibling()->first();`
prevNodes()  | `$node->prevNodes()->get();`
nextNodes()  | `$node->nextNodes()->get();`
leaf()  | `$node->leaf()->first();` | Select ended node
leaves(int $level = null)  | `$node->leaves(2)->first();`
descendants($level, $andSelf, $backOrder)  | `$node->descendants(2, true)->get();` | Get all descendants
whereDescendantOf($id)  | `$node->whereDescendantOf(2)->get();` | Get all descendants
whereNodeBetween([$left, $right]...)  | `$node->whereDescendantOf(2)->get();` | Add node selection statement between specified range.
defaultOrder($dir)  | `$node->defaultOrder(true)->get();` | Add node selection statement between specified range.
byTree($dir)  | `$node->byTree(1)->get();` | Select nodes by `tree_id`.


### Model's helpers
Method | Return | Example
:--- | :--- | :---
isRoot() | bool | `$node->isRoot();`
isChildOf(Model $node) | bool | `$node->isChildOf($parentNode);`
isLeaf() | bool | `$node->isLeaf();`
equalTo(Model $node) | bool | `$node->equalTo($parentNode);`
