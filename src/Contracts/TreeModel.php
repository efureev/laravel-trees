<?php

declare(strict_types=1);

namespace Fureev\Trees\Contracts;

use Fureev\Trees\Collection;
use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\Builder as TreeBuilder;
use Fureev\Trees\Config\Config;
use Fureev\Trees\QueryBuilderV2;
use Fureev\Trees\Relations\AncestorsRelation;
use Fureev\Trees\Relations\DescendantsRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;

/**
 * Phpstan-oriented contract for models that behave as tree nodes.
 *
 * Important: runtime models are not required to implement this interface. Nothing declares
 * `implements TreeModel` and nothing tests for it: `Helper::isTreeNode()` asks whether the model
 * uses `UseTree`, and tells static analysis the answer through an assertion. So this is a
 * description of what a node can do, not a set of methods anyone has to write.
 *
 * It used to describe about a third of them, which meant code typed as `Model&TreeModel` — the
 * delete strategies, the relations, the health checks — could not call the rest without static
 * analysis objecting. Everything the tree traits add is declared here now, and
 * `TreeModelContractTest` fails if the two drift apart.
 *
 * What is deliberately left out: the methods the traits override rather than add — `getDirty()`,
 * `newCollection()`, `newEloquentBuilder()`, `uniqueIds()` — along with the initialiser and
 * booter Laravel calls on a trait. Those belong to Eloquent, and an intersection with `Model`
 * already covers them.
 */
interface TreeModel
{
    // Configuration

    public function getTreeConfig(): Config;

    public function getTreeBuilder(): TreeBuilder;

    public function isMulti(): bool;

    // Column names

    public function leftAttribute(): Attribute;

    public function rightAttribute(): Attribute;

    public function levelAttribute(): Attribute;

    public function parentAttribute(): Attribute;

    public function treeAttribute(): ?Attribute;

    // Column values

    public function leftValue(): int;

    public function rightValue(): int;

    public function levelValue(): int;

    public function parentValue(): int|string|null;

    public function treeValue(): int|string|null;

    /**
     * @return array<int, int|string|null>
     */
    public function getBounds(): array;

    /**
     * @phpstan-param (Model&TreeModel)|string|int $node
     * @return array<int, int|string|null>
     */
    public function getNodeBounds(Model|string|int $node): array;

    // Questions a node answers about itself

    public function isRoot(): bool;

    public function isLeaf(): bool;

    public function isLevel(int $level): bool;

    public function isChildOf(Model $node): bool;

    public function isDescendantOf(Model $node): bool;

    public function isDirectChildOf(Model $node): bool;

    public function isEqualTo(Model $model): bool;

    // Relations

    public function parent(): BelongsTo;

    public function parentWithTrashed(): BelongsTo;

    public function children(): HasMany;

    public function childrenWithTrashed(): HasMany;

    public function ancestors(): AncestorsRelation;

    public function descendants(): DescendantsRelation;

    public function parents(?int $level = null): Collection;

    public function parentByLevel(int $level): ?self;

    public function parentsBuilder(?int $level = null): QueryBuilderV2;

    public function getRoot(): ?static;

    // Positioning

    public function makeRoot(): static;

    public function saveAsRoot(): bool;

    public function appendTo(Model $node): static;

    public function prependTo(Model $node): static;

    public function insertBefore(Model $node): static;

    public function insertAfter(Model $node): static;

    public function up(): bool;

    public function down(): bool;

    public function setTree(string|int $treeId): static;

    // Saving and deleting

    public function forceSave(): bool;

    public function isForceSaving(): bool;

    public function deleteWithChildren(bool $forceDelete = true): mixed;

    public function removeDescendants(): void;

    public function moveChildrenToParent(): void;

    public function restoreWithParents(?string $deletedAt = null): mixed;

    public function restoreWithDescendants(?string $deletedAt = null): mixed;

    // Queries

    public function newNestedSetQuery(?string $table = null): QueryBuilderV2;

    public function newScopedQuery($table = null): QueryBuilderV2;

    public function applyNestedSetScope(QueryBuilderV2 $builder, ?string $table = null): QueryBuilderV2;

    public function getQuery(): Builder;

    public function wrappedTable(): string;

    public function wrappedKey(): string;

    /**
     * @return array{0:string, 1:string}
     */
    public function wrappedColumns(): array;

    // Model events, wired by the trait

    public function beforeInsert(): void;

    public function afterInsert(): void;

    public function beforeUpdate(): void;

    public function afterUpdate(): void;

    public function beforeSave(): void;

    public function afterSave(): void;

    public function beforeDelete(): void;

    public function afterDelete(): void;

    public function beforeRestore(): void;

    public function afterRestore(): void;

    public function trace(): array;
}
