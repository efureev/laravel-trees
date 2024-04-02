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

