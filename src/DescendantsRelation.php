<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;

class DescendantsRelation extends BaseRelation
{

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        if (!static::$constraints) {
            return;
        }

        $this->query->whereDescendantOf($this->parent)->applyNestedSetScope();
    }

    /**
     * @param QueryBuilder $query
     * @param Model $model
     */
    protected function addEagerConstraint($query, $model): void
    {
        $query->whereDescendantOf($model, 'or');
    }

    /**
     * @param Model $model
     * @param NestedSetTrait $related
     *
     * @return mixed
     */
    protected function matches(Model $model, $related): bool
    {
        return $related->isChildOf($model);
    }

    /**
     * @param $hash
     * @param $table
     * @param $lft
     * @param $rgt
     *
     * @return string
     */
    protected function relationExistenceCondition($hash, $table, $lft, $rgt): string
    {
        return "{$hash}.{$lft} between {$table}.{$lft} + 1 and {$table}.{$rgt}";
    }
}
