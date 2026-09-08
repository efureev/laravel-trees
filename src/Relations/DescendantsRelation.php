<?php

declare(strict_types=1);

namespace Fureev\Trees\Relations;

use Fureev\Trees\Config\Helper;
use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model&TreeModel
 *
 * @extends BaseRelation<TModel>
 */
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

    protected function matches(Model $model, Model $related): bool
    {
        if (!Helper::isTreeNode($model) || !Helper::isTreeNode($related)) {
            return false;
        }

        return $related->isChildOf($model);
    }

    protected function addExistenceConstraint(Builder $query, string $hash, string $parentTable): void
    {
        $lft = (string)$this->parent->leftAttribute();
        $rgt = (string)$this->parent->rightAttribute();

        // A descendant opens after the parent and closes before it.
        $query
            ->whereColumn("$hash.$lft", '>', "$parentTable.$lft")
            ->whereColumn("$hash.$lft", '<', "$parentTable.$rgt");
    }
}
