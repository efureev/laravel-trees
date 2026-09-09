<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Helper;
use Illuminate\Database\Eloquent\Model;

/**
 * @method QueryBuilderV2<static> newQuery()
 * @mixin Model
 */
trait WithQueryBuilder
{
    public function newEloquentBuilder($query): QueryBuilderV2
    {
        return new QueryBuilderV2($query);
    }

    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * @return static|null
     */
    public function getRoot(): ?static
    {
        return $this->newQuery()
            ->root()
            ->first();
    }

    /**
     * Is this node inside `$node`'s bounds?
     *
     * The name is narrower than the answer: the check compares bounds, so a grandchild says yes
     * as readily as a child. `isDescendantOf()` is the same check under a name that admits it,
     * and `isDirectChildOf()` is the stricter question this one is often mistaken for.
     *
     * @phpstan-param Model&UseTree $node
     */
    public function isChildOf(Model $node): bool
    {
        return $this->treeValue() === $node->treeValue() &&
            $this->leftValue() > $node->leftValue() &&
            $this->rightValue() < $node->rightValue();
    }

    /**
     * Is this node anywhere inside `$node`'s subtree, at any depth?
     *
     * Same check as `isChildOf()`, named for what it actually answers.
     *
     * @phpstan-param Model&UseTree $node
     */
    public function isDescendantOf(Model $node): bool
    {
        return $this->isChildOf($node);
    }

    /**
     * Is `$node` this node's immediate parent?
     *
     * Reads the parent column rather than the bounds, so it is true one level down and nowhere
     * else. A root answers false for everything, having no parent to match.
     *
     * @phpstan-param Model&UseTree $node
     */
    public function isDirectChildOf(Model $node): bool
    {
        $parent = $this->parentValue();

        return $parent !== null && $parent === $node->getKey();
    }

    /**
     * Is a leaf-node
     */
    public function isLeaf(): bool
    {
        $delta = ($this->rightValue() - $this->leftValue());
        if ($delta === 1) {
            return true;
        }

        if (!$this->isSoftDelete()) {
            return false;
        }

        if ($this->relationLoaded('children')) {
            $children = $this->getRelation('children');
            return $children->count() === 0;
        }

        return $this->children()->count() === 0;
    }

    public function newNestedSetQuery(?string $table = null): QueryBuilderV2
    {
        $builder = $this->isSoftDelete()
            ? $this->withTrashed()
            : $this->newQuery();

        return $this->applyNestedSetScope($builder, $table);
    }

    public function newScopedQuery($table = null): QueryBuilderV2
    {
        return $this->applyNestedSetScope($this->newQuery(), $table);
    }

    public function applyNestedSetScope(QueryBuilderV2 $builder, ?string $table = null): QueryBuilderV2
    {
        if (!$scoped = $this->getScopeAttributes()) {
            return $builder;
        }

        if (!$table) {
            $table = $this->getTable();
        }

        foreach ($scoped as $attribute) {
            $builder->where("$table.$attribute", '=', $this->getAttributeValue($attribute));
        }

        return $builder;
    }

    protected function getScopeAttributes(): array
    {
        return [];
    }

    /**
     * @phpstan-param (Model&UseTree)|string|int $node
     * @return array<int, int|string|null>
     */
    public function getNodeBounds(Model|string|int $node): array
    {
        if (Helper::isTreeNode($node)) {
            /** @var UseTree $node */
            return $node->getBounds();
        }

        return $this->newNestedSetQuery()->getPlainNodeData($node, true);
    }
}
