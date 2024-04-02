# Creating nodes

A Node is usual Laravel Model. First Node is named `Root Node` (or `Root`). The Root node contains other nodes inside.

In a single Tree Mode - you can create only one Root Node. Otherwise, if you want to use several Root-nodes you should
use MultiTree Mode.

## Creating Root-nodes

**In a single-Tree mode:**

You have to force the creation of the first Root Node like this:

```php
Category::make($attributes)->makeRoot()->save(); 
Category::make($attributes)->saveAsRoot();
```

**In a multi-Tree mode:**

You can create Root-node like a usual Laravel manner:

```php
Category::create($attributes);
Category::make($attributes)->save();
```

## Creating sub-nodes

Non-Root Nodes must be appended into another Node (Root- or NonRoot-).

There are several ways to add/create new Non-Nodes to other nodes:

- `PrependTo`: Adds a node inside another node. The Node is inserted BEFORE other children of the parent node.
- `AppendTo`: Adds a node inside another node. The Node is inserted AFTER other children of the parent node.
- `InsertBefore`: Adds child-node into same parent node. The Node is inserted BEFORE target node.
- `InsertAfter`: Adds child-node into same parent node. The Node is inserted AFTER target node.

Examples:

```php
$node->prependTo($parent)->save();
$node->appendTo($parent)->save();

$node->insertBefore($parent)->save();
$node->insertAfter($parent)->save();
```
