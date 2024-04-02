# Model's Helpers

 Method                 | Return          | Example                          
:-----------------------|:----------------|:---------------------------------
 isSoftDelete()         | bool            | `$node->isSoftDelete();`         
 isRoot()               | bool            | `$node->isRoot();`               
 isChildOf(Model $node) | bool            | `$node->isChildOf($parentNode);` 
 isLeaf()               | bool            | `$node->isLeaf();`               
 isLevel()              | bool            | `$node->isLevel($level);`        
 isEqualTo(Model $node) | bool            | `$node->isEqualTo($parentNode);` 
 leftValue()            | int             | `$node->leftValue();`            
 rightValue()           | int             | `$node->rightValue();`           
 levelValue()           | int             | `$node->levelValue();`           
 parentValue()          | ?int            | `$node->parentValue();`          
 treeValue()            | int/string/null | `$node->treeValue();`            
