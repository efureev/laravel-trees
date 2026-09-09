# API Reference

Every public entry point, grouped by what you want to do rather than by class. Signatures are
taken from the source, not from prose.

> [!NOTE]
> "Queries" counts the statements a call issues by itself. Methods returning a query builder
> issue nothing until the query is executed.

## Creating and positioning

Positioning records an intent; the tree changes on the next `save()`.

| Method | Does | Queries |
|---|---|---|
| `makeRoot(): static` | mark the node a root | 0 |
| `saveAsRoot(): bool` | `makeRoot()` and save, or plain save if already a root | 3 |
| `appendTo(Model $node): static` | become the **last** child of `$node` | 0 |
| `prependTo(Model $node): static` | become the **first** child of `$node` | 0 |
| `insertBefore(Model $node): static` | become the sibling before `$node` | 0 |
| `insertAfter(Model $node): static` | become the sibling after `$node` | 0 |
| `forceSave(): bool` | save even when no attribute changed | 3+ |

See [Creating Nodes](./CreatingNodes.md).

## Moving

| Method | Does | Queries |
|---|---|---|
| `up(): bool` | swap with the previous sibling; `false` if there is none | 5 |
| `down(): bool` | swap with the next sibling; `false` if there is none | 5 |
| `setTree(string\|int $treeId): static` | choose the tree; multi-tree only | 0 |

Re-positioning an existing node uses the same methods as creating one. Promoting a node to a
root is `makeRoot()->save()` — multi-tree only, see
[Managing Nodes](./ManagingNodes.md#promote-a-node-to-a-root).

## Deleting

| Method | Does | Queries |
|---|---|---|
| `delete()` | remove the node, lift its children one level | 3 |
| `deleteWithChildren(bool $forceDelete = true): mixed` | remove the node and its subtree | 3+ |
| `removeDescendants(): void` | remove the subtree, keep the node | 1 |
| `moveChildrenToParent(): void` | lift the children one level, keep the node | 2 |

> [!WARNING]
> Always delete through the model. A query-level `delete()` skips the bookkeeping and breaks the
> tree — see [Limitations](./Limitations.md#never-delete-through-a-query).

## Restoring — soft-deleting models

| Method | Brings back |
|---|---|
| `restore()` | that node alone |
| `restoreWithParents(?string $deletedAt = null): mixed` | the node and every trashed ancestor |
| `restoreWithDescendants(?string $deletedAt = null): mixed` | the node and everything trashed beneath it |

Pass a timestamp to touch only nodes trashed at or after it. See
[Soft Deletes](./SoftDeletes.md).

## Relations

| Method | Returns | Queries |
|---|---|---|
| `parent()` | `BelongsTo` — the direct parent | 1 |
| `parentWithTrashed()` | `BelongsTo`, trashed included | 1 |
| `children()` | `HasMany` — direct children, ordered | 1 |
| `childrenWithTrashed()` | `HasMany`, trashed included | 1 |
| `ancestors()` | relation — every ancestor, **root first** | 1 |
| `descendants()` | relation — the whole subtree, any depth, **unordered** | 1 |
| `parents(?int $level = null): Collection` | ancestors as a collection | 1 |
| `parentsBuilder(?int $level = null): QueryBuilderV2` | the same as a builder | 0 |
| `parentByLevel(int $level): ?self` | the **single** ancestor at that level | 1 |

`ancestors` and `descendants` support `has()`, `whereHas()`, `doesntHave()` and `withCount()` —
see [Retrieving Nodes](./ReceivingNodes.md#filtering-by-a-relation).

## Facts about a node

None of these touch the database except where noted.

| Method | Answers |
|---|---|
| `isRoot(): bool` | is `parent_id` null |
| `isLeaf(): bool` | has no children — from the bounds, unless the model soft-deletes |
| `isChildOf(Model $node): bool` | is inside that node's bounds — **any depth** |
| `isEqualTo(Model $model): bool` | same bounds, level, parent and tree |
| `isLevel(int $level): bool` | sits at that level |
| `isMulti(): bool` | is the model configured with a tree column |
| `getRoot(): ?static` | the root of this tree (1 query) |
| `getBounds(): array` | the tree column values, in configuration order |

## Values and column names

| Value | Column |
|---|---|
| `leftValue(): int` | `leftAttribute(): Attribute` |
| `rightValue(): int` | `rightAttribute(): Attribute` |
| `levelValue(): int` | `levelAttribute(): Attribute` |
| `parentValue(): string\|int\|null` | `parentAttribute(): Attribute` |
| `treeValue(): string\|int\|null` | `treeAttribute(): ?Attribute` |

An `Attribute` casts to its column name: `(string)$node->leftAttribute()`.

## Query scopes

On the model statically, or on any `QueryBuilderV2`.

| Method | Narrows to |
|---|---|
| `root()` | root nodes |
| `notRoot()` | everything but the roots |
| `byTree(string\|int $treeId)` | one tree |
| `byLevel(?int $level)` | exactly that level |
| `toLevel(?int $level)` | that level and everything above it |
| `byParent(Model\|string\|int\|null $parent)` | the direct children of a node |
| `leaf()` | nodes with no children |
| `leaves(?int $level = null)` | the leaves of a subtree |
| `defaultOrder(int $dir = SORT_ASC)` | ordered by `lft` — **replaces** any existing ordering |

## Navigating from a node

| Method | Gives |
|---|---|
| `prev()` / `next()` | the node immediately before / after, anywhere in the tree |
| `prevNodes()` / `nextNodes()` | everything before / after; `nextNodes()` **includes the node's own descendants** |
| `siblings()` / `siblingsAndSelf()` | nodes sharing a parent |
| `prevSibling()` / `nextSibling()` | the adjacent sibling |
| `prevSiblings()` / `nextSiblings()` | siblings before / after |
| `descendantsQuery(?int $level = null, bool $andSelf = false, bool $backOrder = false)` | the subtree, optionally depth-limited |
| `parents(?int $level = null, bool $andSelf = false)` | the ancestors |
| `parentsByModelId(string\|int $modelId, ?int $level = null, bool $andSelf = false)` | ancestors of another node, by its id, in one query |
| `whereDescendantOf(...)` / `whereAncestorOf(...)` | bound conditions for composing queries |

## Collections

Queries return `Fureev\Trees\Collection`.

| Method | Does | Queries |
|---|---|---|
| `toTree(Model\|string\|int\|null $fromNode = null, bool $setParentRelations = false): static` | link the nodes and return the roots | 0 |
| `toBreadcrumbs(Model\|string\|int\|null $fromNode = null): static` | fetch missing ancestors, then link | 1 |
| `getRoots(): static` | the nodes with no parent | 0 |
| `linkNodes(bool $setParentRelations = true): static` | fill in `parent`/`children` | 0 |
| `fillMissingIntermediateNodes(): void` | add the absent ancestors | 1 |
| `totalCount(): int` | how many nodes went into `toTree()` | 0 |

See [Collections](./Collections.md).

## Schema

```php
use Fureev\Trees\Database\Migrate;
```

| Method | Does |
|---|---|
| `Migrate::columnsFromModel(Blueprint $table, Model\|string $model, bool $excludeTreeCol = false): Builder` | add the tree columns and indexes from a model |
| `(new Migrate($builder, $table))->buildColumns(bool $excludeTreeCol = false): void` | the same from a builder |
| `(new Migrate($builder, $table))->dropColumns(): void` | drop the columns and their indexes |

See [Database Migration](./Migration.md).

## Integrity

```php
use Fureev\Trees\Healthy\HealthyChecker;
```

| Method | Returns |
|---|---|
| `check(): array` | errors per check |
| `getTotalErrors(): int` | their sum |
| `isBroken(): bool` | whether the sum is above zero |

The checks: `OddnessCheck`, `DuplicatesCheck`, `WrongParentCheck`, `MissingParentCheck` — each
usable on its own with `->check()`.

> [!WARNING]
> `HealthyChecker` runs the first three only. Orphaned nodes pass unnoticed — see
> [Troubleshooting](./Troubleshooting.md#diagnosing-a-broken-tree).

## Repair

| Method | Does |
|---|---|
| `fixTree(?Model $root = null): int` | rebuild the bounds from the parent links |
| `fixSubTree(Model $root): int` | the same for one subtree |
| `fixMultiTree(): array` | the same for every tree in the table |
| `makeGap(int $cut, int $height): int` | shift every bound at or past `$cut` |

> [!WARNING]
> The repair trait carries the author's note that it is unverified since v5.

## Console output

```php
use Fureev\Trees\Table;
```

| Method | Does |
|---|---|
| `Table::fromModel(Model $model): Table` | build from a node and its subtree |
| `Table::fromTree(Collection $collection): Table` | build from a linked collection |
| `(new Table())->fromQuery(QueryBuilderV2 $query): Table` | build from a query — **not static**, unlike the two above |
| `setExtraColumns(array $columns): Table` | pick the columns to print |
| `hideLevel(): Table` | drop the level column |
| `setOffset(string $offset): Table` | change the indent |
| `draw(?OutputInterface $output = null): void` | render |

See [Console Commands](./Console.md).

## Configuration

```php
use Fureev\Trees\Config\{Builder, Attribute, AttributeType, FieldType};
```

| Method | Does |
|---|---|
| `Builder::default(): self` | single tree, default columns |
| `Builder::defaultMulti(): self` | adds the tree column |
| `Builder::make(): self` | nothing preset — every attribute must be supplied |
| `setAttribute(Attribute $attribute): self` | replace one column |
| `setAttributes(Attribute ...$attributes): self` | replace several |
| `left() / right() / level() / parent() / tree()` | the current `Attribute` objects |
| `isMulti(): bool` | is a tree column configured |
| `setDeleterWithChildren(string $value): static` | swap the `deleteWithChildren()` strategy |
| `setChildrenHandlerOnDelete(string $value): static` | swap what happens to children on delete |
| `Attribute::make(AttributeType $name, FieldType $type = FieldType::UnsignedInteger)` | describe one column |
| `setColumnName(string $column): static` | rename it |
| `setNullable(bool $isNull = true): static` | make it nullable |

`AttributeType`: `Left`, `Right`, `Level`, `Parent`, `Tree`.
`FieldType`: `UnsignedSmallInteger`, `UnsignedMediumInteger`, `UnsignedInteger`,
`UnsignedBigInteger`, `UUID`, `ULID`.

See [Advanced Tree Configuration](./AdvancedTreeConfig.md).

## Exceptions

All extend `Fureev\Trees\Exceptions\Exception`.

| Exception | Raised when |
|---|---|
| `UniqueRootException` | a second root is created in a single tree |
| `NotSupportedException` | a node is saved with no position decided |
| `DeleteRootException` | the root of a single tree is deleted |
| `DeletedNodeHasChildrenException` | a root with children is deleted |
| `TreeNeedValueException` | a tree id is required and cannot be generated |
| `UnsavedNodeException` | an unsaved node is used as a target |

## Internal

Present and public, but not for application code — they are the event handlers the package wires
to the model, and helpers for its own queries:

`bootUseNestedSet()`, `beforeInsert()`, `afterInsert()`, `beforeUpdate()`, `afterUpdate()`,
`beforeSave()`, `afterSave()`, `beforeDelete()`, `afterDelete()`, `beforeRestore()`,
`afterRestore()`, `getDirty()`, `newEloquentBuilder()`, `newCollection()`, `trace()`,
`treeCondition()`, `applyNestedSetScope()`, `whereNodeBetween()`, `getNodeData()`,
`getPlainNodeData()`, `getNodeBounds()`, `wrappedColumns()`, `wrappedKey()`, `wrappedTable()`,
`newNestedSetQuery()`, `newScopedQuery()`, `isForceSaving()`.

Calling them directly bypasses the bookkeeping described in
[Architecture](./Architecture.md).
