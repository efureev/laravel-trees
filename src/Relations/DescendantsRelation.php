<?php

declare(strict_types=1);

namespace Fureev\Trees\Relations;

use Fureev\Trees\QueryBuilderV2;
use Fureev\Trees\UseNestedSet;
use Illuminate\Database\Eloquent\Model;

class DescendantsRelation extends BaseRelation
{
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (!static::$constraints) {
            return;
        }

        $this->query->whereDescendantOf($this->parent)->applyNestedSetScope();
    }

    protected function addEagerConstraint(QueryBuilderV2 $query, Model $model): void
    {
        $query->whereDescendantOf($model, 'or');
    }

    /**
     * @param Model $model
     * @param Model|UseNestedSet $related
     *
     * @return mixed
     */
    protected function matches(Model $model, Model $related): bool
    {
        return $related->isChildOf($model);
    }

    protected function relationExistenceCondition(string $hash, string $table, string $lft, string $rgt): string
    {
        return "$hash.$lft between $table.$lft + 1 and $table.$rgt";
    }
}
