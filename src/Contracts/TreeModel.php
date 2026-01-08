<?php

declare(strict_types=1);

namespace Fureev\Trees\Contracts;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\Config;
use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * Phpstan-oriented contract for models that behave as tree nodes.
 *
 * Important: runtime models are not required to implement this interface.
 * It is used for static analysis via assertions in `Helper::isTreeNode()`.
 */
interface TreeModel
{
    public function getTreeConfig(): Config;

    public function isMulti(): bool;

    public function leftAttribute(): Attribute;

    public function rightAttribute(): Attribute;

    public function levelAttribute(): Attribute;

    public function parentAttribute(): Attribute;

    public function treeAttribute(): ?Attribute;

    public function leftValue(): int;

    public function rightValue(): int;

    public function levelValue(): int;

    public function parentValue(): int|string|null;

    public function treeValue(): int|string|null;

    public function isRoot(): bool;

    public function isChildOf(Model $node): bool;

    public function moveChildrenToParent(): void;

    /**
     * @return array<int, int|string|null>
     */
    public function getBounds(): array;

    /**
     * @phpstan-param (Model&TreeModel)|string|int $node
     * @return array<int, int|string|null>
     */
    public function getNodeBounds(Model|string|int $node): array;

    public function newNestedSetQuery(?string $table = null): QueryBuilderV2;

    public function applyNestedSetScope(QueryBuilderV2 $builder, ?string $table = null): QueryBuilderV2;

    public function parentsBuilder(?int $level = null): QueryBuilderV2;

    public function wrappedTable(): string;

    public function wrappedKey(): string;

    /**
     * @return array{0:string, 1:string}
     */
    public function wrappedColumns(): array;

    public function getQuery(): Builder;
}
