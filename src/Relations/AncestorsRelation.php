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
class AncestorsRelation extends BaseRelation
{
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (!static::$constraints) {
            return;
        }

        $this->query->whereAncestorOf($this->parent)->applyNestedSetScope();
    }

    protected function addEagerConstraint(QueryBuilderV2 $query, Model $model): void
    {
        $query->whereAncestorOf($model, 'or');
    }

    protected function matches(Model $model, Model $related): bool
    {
        if (!Helper::isTreeNode($model) || !Helper::isTreeNode($related)) {
            return false;
        }

        // An ancestor ($related) contains the node ($model) within its bounds.
        return $model->isChildOf($related);
    }

    protected function addExistenceConstraint(Builder $query, string $hash, string $parentTable): void
    {
        $lft = (string)$this->parent->leftAttribute();
        $rgt = (string)$this->parent->rightAttribute();

        // An ancestor contains the node: it opens before it and closes after it.
        $query
            ->whereColumn("$hash.$lft", '<', "$parentTable.$lft")
            ->whereColumn("$hash.$rgt", '>', "$parentTable.$rgt");
    }
}
