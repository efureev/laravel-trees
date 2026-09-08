<?php

declare(strict_types=1);

namespace Fureev\Trees\Relations;

use Fureev\Trees\Collection;
use Fureev\Trees\Config\Helper;
use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * @template TModel of Model&TreeModel
 *
 * @extends Relation<TModel, TModel, Collection<int, TModel>>
 *
 * @property QueryBuilderV2<TModel> $query
 */
abstract class BaseRelation extends Relation
{
    public function __construct(QueryBuilderV2 $builder, Model $parent)
    {
        if (!Helper::isTreeNode($parent)) {
            throw new InvalidArgumentException('Model must be a node.');
        }

        parent::__construct($builder, $parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation): array
    {
        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return Collection<int, TModel>
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     */
    public function addEagerConstraints(array $models): void
    {
        // The first model in the array is always the parent, so add the scope constraints based on that model.
        // @link https://github.com/laravel/framework/pull/25240
        // @link https://github.com/lazychaser/laravel-nestedset/issues/351
        $firstModel = ($models[0] ?? null);
        if (Helper::isTreeNode($firstModel)) {
            $firstModel->applyNestedSetScope($this->query);
        }
        $this->query->whereNested(
            function (Builder $inner) use ($models) {
                // We will use this query in order to apply constraints to the
                // base query builder
                $outer = $this->parent->newQuery()->setQuery($inner);
                foreach ($models as $model) {
                    $this->addEagerConstraint($outer, $model);
                }
            }
        );
    }

    abstract protected function addEagerConstraint(QueryBuilderV2 $query, Model $model): void;

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param Model[] $models
     * @param EloquentCollection $results
     * @param string $relation
     */
    public function match(array $models, EloquentCollection $results, $relation): array
    {
        foreach ($models as $model) {
            $related = $this->matchForModel($model, $results);
            $model->setRelation($relation, $related);
        }

        return $models;
    }

    /**
     * @return EloquentCollection
     */
    protected function matchForModel(Model $model, EloquentCollection $results): EloquentCollection
    {
        $result = $this->related->newCollection();
        foreach ($results as $related) {
            if ($this->matches($model, $related)) {
                $result->push($related);
            }
        }

        return $result;
    }

    abstract protected function matches(Model $model, Model $related): bool;

    /**
     * Constrain the aliased related table against the parent table by nested set bounds.
     *
     * @param EloquentBuilder $query Query over the related table, aliased as $hash
     */
    abstract protected function addExistenceConstraint(
        EloquentBuilder $query,
        string $hash,
        string $parentTable
    ): void;

    /**
     * Build the subquery behind has() / whereHas() / doesntHave() / withCount().
     *
     * @param EloquentBuilder<TModel> $query
     * @param EloquentBuilder<TModel> $parentQuery
     * @param mixed $columns
     *
     * @return EloquentBuilder<TModel>
     */
    public function getRelationExistenceQuery(
        EloquentBuilder $query,
        EloquentBuilder $parentQuery,
        $columns = ['*']
    ) {
        $parentTable = $this->parent->getTable();
        $hash        = $this->getRelationCountHash();

        // This is a self relation, so the related table has to be aliased. It cannot be
        // renamed on the related model itself the way HasOneOrMany does it: the relation is
        // built from the parent's own query, so Relation::__construct() picked up the very
        // same object as $this->related, and setTable() would rename the outer query too.
        // The clone also keeps lazily applied global scopes (SoftDeletingScope qualifies
        // its column at execution time) pointing at the alias rather than at a table that
        // is no longer in the FROM clause.
        $related = clone $query->getModel();
        $related->setTable($hash);

        // setModel() resets the FROM to the model table, so the alias is applied after it.
        $query->setModel($related);
        $query->from("$parentTable as $hash");

        $this->addExistenceConstraint($query, $hash, $parentTable);

        // Bounds alone are not enough: every root starts at lft = 1, so they overlap
        // between trees.
        if ($this->parent->isMulti()) {
            $tree = (string)$this->parent->treeAttribute();
            $query->whereColumn("$hash.$tree", '=', "$parentTable.$tree");
        }

        // Assigned rather than returned inline: Laravel types select() as a query builder.
        $query->select($columns);

        return $query;
    }
}
