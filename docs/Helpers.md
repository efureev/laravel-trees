# Model's Helpers

 Method                 | Return          | Example                          
:-----------------------|:----------------|:---------------------------------
 isRoot()               | bool            | `$node->isRoot();`               
 isChildOf(Model $node) | bool            | `$node->isChildOf($ancestor);`   
 isLeaf()               | bool            | `$node->isLeaf();`               
 isLevel(int $level)    | bool            | `$node->isLevel($level);`        
 isEqualTo(Model $node) | bool            | `$node->isEqualTo($otherNode);`  
 leftValue()            | int             | `$node->leftValue();`            
 rightValue()           | int             | `$node->rightValue();`           
 levelValue()           | int             | `$node->levelValue();`           
 parentValue()          | int/string/null | `$node->parentValue();`          
 treeValue()            | int/string/null | `$node->treeValue();`            

`isChildOf()` answers "is this node inside that node's bounds", so it is true for a descendant at
any depth, not only for a direct child.

## Checking for soft deletes

`isSoftDelete()` is `protected` and cannot be called from outside the model — doing so ends up in
Eloquent's `__call` and throws `BadMethodCallException`. Use either of these instead:

```php
use Fureev\Trees\Config\Helper;

Helper::isModelSoftDeletable($node);      // also accepts a class-string
$node->getTreeConfig()->isSoftDelete;     // public readonly property
```
