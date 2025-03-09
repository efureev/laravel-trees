# Health-ing And Fixing

## Checking consistency

You can check whether a tree is broken (i.e. has some structural errors):

> Use helper class `HealthyChecker`

```php
$checker = new HealthyChecker(Category::class);
$broken = $checker->isBroken();
```

Or use specify checker:

```php
$checker = new DuplicatesCheck(Category::class);
$errorsCount = $checker->check();
```

It is possible to get error statistics:

```php
$checker = new HealthyChecker(Category::class);
$checker->check();
```

List of checkers:

- `OddnessCheck` - the number of nodes that have wrong set of `lft` and `rgt` values
- `DuplicatesCheck` - the number of nodes that have same `lft` or `rgt` values
- `WrongParentCheck` - the number of nodes that have invalid `parent_id` value that doesn't correspond to `lft` and `rgt`
  values
- `MissingParentCheck` - the number of nodes that have `parent_id` pointing to node that doesn't exists


## Fixing tree

> **Since v5 this feature is not tested!**

> Use trait `Fixing` with `QueryBuilderV2`

For single tree:

```php
Node::fixTree();
```

For multi tree:

```php
Node::fixMultiTree();
```

