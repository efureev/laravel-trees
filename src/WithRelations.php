<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Collection $ancestors
 * @property Collection $descendants
 * @property Collection $children
 * @property Collection $childrenWithTrashed
 * @property ?Model $parentWithTrashed
 */
trait WithRelations
{
    /**
     * Relation to the parent.
     */
    public function parent(): BelongsTo
    {
        return $this
            ->belongsTo(static::class, (string)$this->parentAttribute())
            ->setModel($this);
    }

    /**
     * @param int|null $level
     *
     * @return Model[]|Collection<Model>
     */
    public function parents(?int $level = null): Collection
    {
        return $this->parentsBuilder($level)->get();
    }

    /**
     * @param ?int $level
     *
     * @return QueryBuilderV2
     */
    public function parentsBuilder(?int $level = null): QueryBuilderV2
    {
        return $this
            ->newQuery()
            ->parents($level);
    }

    public function parentByLevel(int $level): ?self
    {
        return $this->parents($level)->first();
    }

    /**
     * Relation to the parent.
     */
    public function parentWithTrashed(): BelongsTo
    {
        $query = $this->parent();

        if ($this->isSoftDelete()) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Relation to children. Return direct children
     */
    public function children(): HasMany
    {
        return $this
            ->hasMany($this::class, (string)$this->parentAttribute())
            ->setModel($this);
    }

    public function childrenWithTrashed(): HasMany
    {
        $query = $this->children();

        if ($this->isSoftDelete()) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Get query for the all descendants of the node
     */
    public function descendants(): Relations\DescendantsRelation
    {
        return new Relations\DescendantsRelation($this->newQuery(), $this);
    }

    public function ancestors(): Relations\AncestorsRelation
    {
        return new Relations\AncestorsRelation($this->newQuery(), $this);
    }
}
