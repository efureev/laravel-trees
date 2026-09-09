# Model Helpers

Questions a node can answer about itself. None of these touch the database unless noted.

## Position in the tree

| Method | Answers |
|---|---|
| `isRoot(): bool` | is `parent_id` null |
| `isLeaf(): bool` | has no children |
| `isLevel(int $level): bool` | sits at that level |
| `isChildOf(Model $node): bool` | is inside that node's bounds |
| `isEqualTo(Model $node): bool` | same bounds, level, parent and tree |
| `isMulti(): bool` | is the model configured with a tree column |
| `getRoot(): ?static` | the root of this tree — **one query** |

```php
$node->isRoot();
$node->isLeaf();
$node->isChildOf($ancestor);
```

> [!NOTE]
> `isChildOf()` compares bounds, so it is true for a descendant at **any** depth, not only a
> direct child. `isLeaf()` also answers from the bounds — except on a soft-deleting model, where
> it falls back to counting children.

## Values

| Method | Returns |
|---|---|
| `leftValue(): int` | the left bound |
| `rightValue(): int` | the right bound |
| `levelValue(): int` | the depth, `0` at the root |
| `parentValue(): int\|string\|null` | the parent key, `null` at a root |
| `treeValue(): int\|string\|null` | the tree id, `null` on a single tree |
| `getBounds(): array` | all of the above, in configuration order |

## Column names

Each value has a matching accessor returning the `Attribute` that describes its column. An
`Attribute` casts to its column name, which keeps code working after a rename:

```php
(string)$node->leftAttribute();    // 'lft', or whatever it was renamed to
(string)$node->rightAttribute();
(string)$node->levelAttribute();
(string)$node->parentAttribute();
(string)$node->treeAttribute();    // null on a single tree
```

## Checking for soft deletes

`isSoftDelete()` is `protected` and cannot be called from outside the model — the call falls
through to Eloquent's `__call` and throws `BadMethodCallException`. Use either of these:

```php
use Fureev\Trees\Config\Helper;

Helper::isModelSoftDeletable($node);      // also accepts a class-string
$node->getTreeConfig()->isSoftDelete;     // public readonly property
```

## Related

- [Retrieving Nodes](./ReceivingNodes.md) — reading other nodes
- [API Reference](./ApiReference.md) — the complete surface
