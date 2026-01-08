<?php

declare(strict_types=1);

namespace Fureev\Trees\Relations;

use Fureev\Trees\Config\Helper;
use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
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
        $query->whereAncestorOf($model);
    }

    protected function matches(Model $model, Model $related): bool
    {
        if (!Helper::isTreeNode($model) || !Helper::isTreeNode($related)) {
            return false;
        }

        return $related->isChildOf($model);
    }

    protected function relationExistenceCondition(string $hash, string $table, string $lft, string $rgt): string
    {
        return "$hash.$lft between $table.$lft + 1 and $table.$rgt";
    }
}
