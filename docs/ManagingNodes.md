# Managing nodes

## Move Nodes

### Move a Node up in self parent scope

```php
$node->up();
```

### Move a Node down in self parent scope

```php
$node->down();
```

## Delete Nodes

**Basic**

Remove a target Node only. All it's descendants will be moved to target-node's parent (default behavior).

```php
$node->delete();
```

**NB** To change strategy about handle children, you should set up Tree Builder's
prop `childrenHandlerOnDelete`. By default,
it uses `\Fureev\Trees\Strategy\MoveChildrenToParent` handler.

**WithChildren**

Delete a target node with all it's descendants (include deep-included).

```php
$node->deleteWithChildren();
```

**NB** It's a default behavior. To change strategy
about handle children, you should set up Tree Builder's prop `deleterWithChildren`. By default,
it uses `\Fureev\Trees\Strategy\DeleteWithChildren` handler.

**IMPORTANT!** All node's children delete by Query (not thought Model)!


***

**IMPORTANT!** Nodes are required to be deleted as models! **DO NOT** try to delete them using a query like so:

```php
Category::where('id', '=', $id)->delete();
```

**This will break the tree!**

## Delete SoftDeletable Models

The Tree works normally with `SoftDelete` trait.

## Promote a Node to a Root

An existing node can become the root of its own tree. The whole subtree travels with it: the
descendants keep their shape and move into the new tree.

```php
$node->makeRoot()->save();
// or, equivalently
$node->saveAsRoot();
```

The node ends up with `lft = 1`, `lvl = 0`, no parent, and a freshly generated tree id. The gap
it leaves behind in the old tree is closed.

To place it into a tree you choose rather than a generated one, set the value first:

```php
$node->setTree(42)->makeRoot()->save();
```

**NB** This is a multi-tree operation. On a single-tree model it throws
`Can not move a node as the root when Model is not set to "MultiTree"`, since a single tree has
room for only one root.

