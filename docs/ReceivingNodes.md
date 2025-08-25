# Receiving nodes

> In some cases we will use an `$id` variable which is an id of the target node.

## Ways to get nodes

- `parent` - To get a Parent Node
- `parents` - To get a Chain of Parent Nodes (till Root)
- `parentByLevel` - To get a Chain of Parent Nodes (till specified level)
- `parentWithTrashed` - To get a Chain of Parent Nodes with Trashed Nodes
- `children` - To get a Collection of direct descendants
- `childrenWithTrashed` - To get a Collection of direct descendants with Trashed Nodes
- `descendants` - To get a Collection of all descendants in Laravel-Relation manner
- `ancestors` - To get a Chain of Parent Nodes (till Root) in Laravel-Relation manner
- `prev` -
- `next` -
- `prevNodes` -
- `nextNodes` -
- `siblings` -
- `prevSibling` -
- `prevSiblings` -
- `nextSibling` -
- `nextSiblings` -
- `leaves` -
- `leaf` -

### To get a Parent Node

> @return Model

```php
$parent = $node->parent;
# it's equal to
$parent = $node->parent()->first();
```

### To get Parents chains

> @return Collection

```php
$parents = $node->parents();
```

### To get a Chain of Parent Nodes (till specified level)

> @return Collection

```php
$parents = $node->parentByLevel(1);
# it's equal to
$parents = $node->parents(1);
```

### To get a Collection of direct descendants

> @return Collection

```php
$children = $node->children;
# it's equal to
$children = $node->children()->get();
```

You can use relation as usual:

```php
$node->children()->save($subNode);
$subNode->children()->save($subSubNode);
```

### To get a Collection of direct descendants with Trashed Nodes

> @return Collection

```php
$children = $node->childrenWithTrashed;
# it's equal to
$children = $node->childrenWithTrashed()->get();
# it's equal to
$children = $node->children()->withTrashed()->get();
```

### To get a Collection of all descendants in Laravel-Relation manner

> @return Collection

```php
$children = $node->descendants;
# it's equal to
$children = $node->descendants()->get();
```

### To get a Chain of Parent Nodes (till Root) in Laravel-Relation manner

> @return Collection

```php
$children = $node->descendants;
# it's equal to
$children = $node->descendants()->get();
```

### Siblings

Siblings are nodes that have same parent.

```php
// Get all siblings of the node
$collection = $node->siblings()->get();

// Get siblings which are before the node
$collection = $node->prevSiblings()->get();

// Get siblings which are after the node
$collection = $node->nextSiblings()->get();

// Get a sibling that is immediately before the node
$prevNode = $node->prevSibling()->first();

// Get a sibling that is immediately after the node
$nextNode = $node->nextSibling()->first();
```

```php
$prevNode = $node->prev()->first();
$nextNode = $node->next()->first();
```

## Receiving through Queries without Models

### root

Returns a query for root nodes.

```php
MultiCategory::root();
```

### notRoot

Returns a query for non-root nodes.

```php
MultiCategory::notRoot();
```

### parentsByModelId

Returns a collection of parents of the node with the specified id.

NB: In progress. Works only for multi-tree nodes.

```php
MultiCategory::parentsByModelId($node31->id)->get();
MultiCategory::parentsByModelId($node31->id, level: 1)->get();
MultiCategory::parentsByModelId($node31->id, andSelf: true)->get();
```

### byTree

Returns a query for nodes of the specified tree.

```php
MultiCategory::byTree($id);
MultiCategory::byTree($id)->get();
```

### toLevel

Returns a query for nodes of the specified level.

```php
Category::toLevel(1);
```

### byParent

Returns a query for nodes of the specified parent.

```php
Category::byParent($pid);
```
